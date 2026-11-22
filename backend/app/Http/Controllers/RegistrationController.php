<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(private RegistrationService $service) {}

    public function mine(Request $r)
    {
        return Registration::with('event.category')
            ->where('user_id', $r->user()->id)
            ->orderByDesc('id')->get()
            ->map(fn (Registration $g) => $g->present($g->event) + [
                'event' => [
                    'id' => $g->event->id,
                    'title' => $g->event->title,
                    'location' => $g->event->location,
                    'starts_at' => $g->event->starts_at->toIso8601String(),
                    'category' => $g->event->category?->name,
                ],
            ]);
    }

    public function store(Request $r, int $id)
    {
        [$reg, $event] = $this->service->register($r->user(), $id);

        return response()->json([
            'message' => $reg->status === 'confirmed'
                ? 'You are registered. Your seat is confirmed.'
                : 'This event is full. You have been added to the waitlist.',
            'registration' => $reg->present($event),
        ], 201);
    }

    public function destroy(Request $r, int $id)
    {
        [$reg, $event] = $this->service->cancel($r->user(), $id);

        return ['message' => 'Your registration has been cancelled.', 'registration' => $reg->present($event)];
    }
}
