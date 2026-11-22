<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampusEventTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'student'): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid().'@t.test', 'password' => 'password', 'role' => $role]);
    }

    private function event(User $org, array $o = []): Event
    {
        $cat = EventCategory::firstOrCreate(['slug' => 'tech'], ['name' => 'Tech']);
        return Event::create($o + [
            'organizer_id' => $org->id, 'category_id' => $cat->id, 'title' => 'Test', 'description' => 'd',
            'location' => 'Hall', 'starts_at' => now()->addDays(10),
            'registration_closes_at' => now()->addDays(8), 'cancellation_closes_at' => now()->addDays(5),
            'capacity' => 1, 'status' => 'published',
        ]);
    }

    // ---------- Capacity ----------
    public function test_last_seat_goes_to_one_student_and_the_rest_are_waitlisted(): void
    {
        $event = $this->event($this->user('organizer'), ['capacity' => 1]);
        $statuses = [];
        foreach (range(1, 3) as $_) {
            Sanctum::actingAs($this->user());
            $statuses[] = $this->postJson("/api/events/{$event->id}/register")->assertCreated()->json('registration.status');
        }
        $this->assertSame(['confirmed', 'waitlisted', 'waitlisted'], $statuses);
        $this->assertSame(1, Registration::where('event_id', $event->id)->where('status', 'confirmed')->count());
    }

    public function test_cancelling_a_confirmed_seat_promotes_the_oldest_waitlisted_student(): void
    {
        $event = $this->event($this->user('organizer'), ['capacity' => 1]);
        [$a, $b, $c] = [$this->user(), $this->user(), $this->user()];
        $regs = [];
        foreach ([$a, $b, $c] as $u) {
            Sanctum::actingAs($u);
            $regs[] = $this->postJson("/api/events/{$event->id}/register")->json('registration.id');
        }
        Sanctum::actingAs($a);
        $this->deleteJson("/api/registrations/{$regs[0]}")->assertOk();

        $this->assertSame('confirmed', Registration::find($regs[1])->status);
        $this->assertSame('waitlisted', Registration::find($regs[2])->status);
    }

    public function test_cannot_register_after_cutoff_or_for_unpublished_event(): void
    {
        $org = $this->user('organizer');
        $late = $this->event($org, ['registration_closes_at' => now()->subHour()]);
        $draft = $this->event($org, ['status' => 'draft']);
        Sanctum::actingAs($this->user());
        $this->postJson("/api/events/{$late->id}/register")->assertStatus(422);
        $this->postJson("/api/events/{$draft->id}/register")->assertNotFound();
    }

    // ---------- Duplicates ----------
    public function test_duplicate_active_registration_is_rejected_but_reregistering_after_cancel_works(): void
    {
        $event = $this->event($this->user('organizer'), ['capacity' => 5]);
        $s = $this->user();
        Sanctum::actingAs($s);
        $id = $this->postJson("/api/events/{$event->id}/register")->assertCreated()->json('registration.id');
        $this->postJson("/api/events/{$event->id}/register")->assertStatus(409);

        $this->deleteJson("/api/registrations/{$id}")->assertOk();
        $this->postJson("/api/events/{$event->id}/register")->assertCreated();
        $this->assertSame(1, Registration::where('user_id', $s->id)->where('active', 1)->count());
    }

    public function test_cancellation_after_window_is_rejected(): void
    {
        $event = $this->event($this->user('organizer'), ['capacity' => 5, 'cancellation_closes_at' => now()->subMinute()]);
        $s = $this->user();
        $reg = Registration::create(['event_id' => $event->id, 'user_id' => $s->id, 'status' => 'confirmed', 'active' => 1]);
        Sanctum::actingAs($s);
        $this->deleteJson("/api/registrations/{$reg->id}")->assertStatus(422);
        $this->assertSame('confirmed', $reg->fresh()->status);
    }

    // ---------- Authorization ----------
    public function test_guest_gets_401_on_protected_routes(): void
    {
        $this->getJson('/api/my/registrations')->assertUnauthorized();
        $this->getJson('/api/organizer/events')->assertUnauthorized();
    }

    public function test_student_cannot_use_organizer_endpoints(): void
    {
        Sanctum::actingAs($this->user());
        $this->getJson('/api/organizer/events')->assertForbidden();
        $this->postJson('/api/organizer/events', [])->assertForbidden();
    }

    public function test_organizer_cannot_register_as_student(): void
    {
        $event = $this->event($org = $this->user('organizer'));
        Sanctum::actingAs($org);
        $this->postJson("/api/events/{$event->id}/register")->assertForbidden();
    }

    public function test_student_cannot_cancel_another_students_registration(): void
    {
        $event = $this->event($this->user('organizer'), ['capacity' => 5]);
        $owner = $this->user();
        $reg = Registration::create(['event_id' => $event->id, 'user_id' => $owner->id, 'status' => 'confirmed', 'active' => 1]);
        Sanctum::actingAs($this->user());
        $this->deleteJson("/api/registrations/{$reg->id}")->assertNotFound();
        $this->assertSame('confirmed', $reg->fresh()->status);
    }

    public function test_organizer_cannot_manage_another_organizers_event(): void
    {
        $event = $this->event($this->user('organizer'));
        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/events/{$event->id}/close")->assertForbidden();
        $this->getJson("/api/organizer/events/{$event->id}/registrations")->assertForbidden();
    }

    public function test_public_signup_cannot_create_an_organizer(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Eve', 'email' => 'eve@t.test', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'organizer',
        ])->assertCreated()->assertJsonPath('user.role', 'student');
    }

    // ---------- Organizer ----------
    public function test_raising_capacity_promotes_waitlist_and_lowering_below_confirmed_fails(): void
    {
        $org = $this->user('organizer');
        $event = $this->event($org, ['capacity' => 1]);
        foreach ([$this->user(), $this->user()] as $u) {
            Sanctum::actingAs($u);
            $this->postJson("/api/events/{$event->id}/register");
        }
        Sanctum::actingAs($org);
        $body = [
            'title' => 'Test', 'description' => 'd', 'location' => 'Hall', 'category_id' => $event->category_id,
            'starts_at' => now()->addDays(10)->toIso8601String(), 'registration_closes_at' => now()->addDays(8)->toIso8601String(),
            'cancellation_closes_at' => now()->addDays(5)->toIso8601String(), 'capacity' => 2,
        ];
        $this->putJson("/api/organizer/events/{$event->id}", $body)->assertOk();
        $this->assertSame(2, Registration::where('event_id', $event->id)->where('status', 'confirmed')->count());

        $this->putJson("/api/organizer/events/{$event->id}", array_merge($body, ["capacity" => 1]))->assertStatus(422);
    }
}
