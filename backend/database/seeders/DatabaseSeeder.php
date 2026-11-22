<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo accounts (fake data only). Password for all: "password"
        $org = User::create(['name' => 'Olivia Organizer', 'email' => 'organizer@campus.test', 'password' => 'password', 'role' => 'organizer']);
        User::create(['name' => 'Omar Organizer', 'email' => 'organizer2@campus.test', 'password' => 'password', 'role' => 'organizer']);
        $students = collect(range(1, 4))->map(fn ($i) => User::create([
            'name' => "Student $i", 'email' => "student$i@campus.test", 'password' => 'password', 'role' => 'student',
        ]));

        $cats = collect(['Academic', 'Career', 'Sports', 'Arts & Culture', 'Tech', 'Social'])
            ->mapWithKeys(fn ($n) => [$n => EventCategory::create(['name' => $n, 'slug' => str($n)->slug()])]);

        $mk = fn ($title, $cat, $days, $cap, $status = 'published', $loc = 'Main Hall') => Event::create([
            'organizer_id' => $org->id, 'category_id' => $cats[$cat]->id, 'title' => $title,
            'description' => "$title - an open campus event. Bring your student ID. Everyone is welcome.",
            'location' => $loc, 'starts_at' => now()->addDays($days)->setTime(14, 0),
            'registration_closes_at' => now()->addDays($days - 1)->setTime(14, 0),
            'cancellation_closes_at' => now()->addDays($days - 2)->setTime(14, 0),
            'capacity' => $cap, 'status' => $status,
        ]);

        $mk('Intro to Laravel Workshop', 'Tech', 10, 30, 'published', 'Lab 3');
        $mk('Career Fair 2026', 'Career', 14, 200, 'published', 'Sports Complex');
        $mk('Inter-Department Cricket Cup', 'Sports', 7, 50, 'published', 'Main Ground');
        $mk('Open Mic Night', 'Arts & Culture', 12, 40, 'published', 'Auditorium');
        $mk('Research Poster Session', 'Academic', 20, 60, 'draft', 'Library');

        // Tiny event so you can demo waitlist + promotion immediately
        $full = $mk('Hackathon Kick-off (2 seats)', 'Tech', 9, 2, 'published', 'Innovation Hub');
        foreach ([[0, 'confirmed'], [1, 'confirmed'], [2, 'waitlisted']] as [$i, $st]) {
            Registration::create(['event_id' => $full->id, 'user_id' => $students[$i]->id, 'status' => $st, 'active' => 1]);
        }
    }
}
