<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerEventController extends Controller
{
    public function __construct(private RegistrationService $service) {}

    public function index(Request $r)
    {
        return Event::with('category')->withSeatCounts()
            ->where('organizer_id', $r->user()->id)->orderByDesc('starts_at')->get()
            ->map(fn (Event $e) => $e->present());
    }

    public function store(Request $r)
    {
        $data = $this->validated($r, true);
        $event = Event::create($data + ['organizer_id' => $r->user()->id, 'status' => 'draft']);

        return response()->json($event->load('category')->present(), 201);
    }

    public function update(Request $r, int $id)
    {
        $event = $this->owned($r, $id);
        $data = $this->validated($r, false);

        DB::transaction(function () use ($event, $data) {
            $event = Event::whereKey($event->id)->lockForUpdate()->first();
            $confirmed = Registration::where('event_id', $event->id)->where('status', 'confirmed')->count();

            if ($data['capacity'] < $confirmed) {
                throw new ApiException("Capacity cannot be lower than the {$confirmed} confirmed registrations.", 422);
            }
            $event->update($data);
            // Capacity increase => pull people off the waitlist in the same transaction.
            $this->service->promoteWaitlist($event);
        });

        return Event::with('category')->withSeatCounts()->find($id)->present();
    }

    public function publish(Request $r, int $id)
    {
        $event = $this->owned($r, $id);
        if ($event->status === 'closed') {
            throw new ApiException('A closed event cannot be re-published.', 422);
        }
        if ($event->starts_at->isPast()) {
            throw new ApiException('An event that has already started cannot be published.', 422);
        }
        $event->update(['status' => 'published']);
        return $event->load('category')->present();
    }

    public function close(Request $r, int $id)
    {
        $event = $this->owned($r, $id);
        $event->update(['status' => 'closed']);
        return $event->load('category')->present();
    }

    public function registrations(Request $r, int $id)
    {
        $event = $this->owned($r, $id);

        return Registration::with(['user:id,name,email', 'attendance'])
            ->where('event_id', $event->id)->orderBy('id')->get()
            ->map(fn (Registration $g) => [
                'id' => $g->id,
                'student' => ['name' => $g->user->name, 'email' => $g->user->email],
                'status' => $g->status,
                'registered_at' => $g->created_at->toIso8601String(),
                'attended' => (bool) $g->attendance,
            ]);
    }

    public function attendance(Request $r, int $id)
    {
        $data = $r->validate(['attended' => 'required|boolean']);
        $reg = Registration::with('event')->findOrFail($id);

        abort_unless($reg->event->organizer_id === $r->user()->id, 403, 'This is not your event.');
        if ($reg->status !== 'confirmed') {
            throw new ApiException('Only confirmed registrations can be checked in.', 422);
        }

        if ($data['attended']) {
            EventAttendance::updateOrCreate(
                ['registration_id' => $reg->id],
                ['marked_by' => $r->user()->id, 'checked_in_at' => now()]
            );
        } else {
            EventAttendance::where('registration_id', $reg->id)->delete();
        }
        return ['registration_id' => $reg->id, 'attended' => $data['attended']];
    }

    private function owned(Request $r, int $id): Event
    {
        $event = Event::findOrFail($id);
        abort_unless($event->organizer_id === $r->user()->id, 403, 'This is not your event.');
        return $event;
    }

    private function validated(Request $r, bool $creating): array
    {
        return $r->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'location' => 'required|string|max:150',
            'category_id' => 'required|integer|exists:event_categories,id',
            'starts_at' => ['required', 'date', $creating ? 'after:now' : 'date'],
            'registration_closes_at' => 'required|date|before_or_equal:starts_at',
            'cancellation_closes_at' => 'required|date|before_or_equal:starts_at',
            'capacity' => 'required|integer|min:1|max:100000',
        ]);
    }
}
