<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Every seat-changing operation locks the EVENT row first (SELECT ... FOR UPDATE).
 * All register / cancel / capacity changes for one event are therefore serialised, so
 * two requests can never both see "1 seat left" and both take it. Everything runs in
 * one transaction: a failure rolls back the status change AND the waitlist promotion.
 */
class RegistrationService
{
    /** @return array{0: Registration, 1: Event} */
    public function register(User $user, int $eventId): array
    {
        try {
            return DB::transaction(function () use ($user, $eventId) {
                $event = Event::whereKey($eventId)->lockForUpdate()->first();

                if (! $event || $event->status !== 'published') {
                    throw new ApiException('Event not found or not open for registration.', 404);
                }
                if (now()->gte($event->registration_closes_at)) {
                    throw new ApiException('Registration for this event has closed.', 422);
                }
                if (Registration::where('event_id', $event->id)->where('user_id', $user->id)->where('active', 1)->exists()) {
                    throw new ApiException('You already have an active registration for this event.', 409);
                }

                $confirmed = Registration::where('event_id', $event->id)->where('status', 'confirmed')->count();

                $reg = Registration::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => $confirmed < $event->capacity ? 'confirmed' : 'waitlisted',
                    'active' => 1,
                ]);

                return [$reg, $event];
            });
        } catch (UniqueConstraintViolationException) {
            // Backstop: the DB unique index caught a duplicate that slipped past the check.
            throw new ApiException('You already have an active registration for this event.', 409);
        }
    }

    /** @return array{0: Registration, 1: Event} */
    public function cancel(User $user, int $registrationId): array
    {
        return DB::transaction(function () use ($user, $registrationId) {
            // Scoped to the owner: someone else's id simply looks like "not found".
            $owned = Registration::where('id', $registrationId)->where('user_id', $user->id)->first();
            if (! $owned) {
                throw new ApiException('Registration not found.', 404);
            }

            $event = Event::whereKey($owned->event_id)->lockForUpdate()->first();
            $reg = Registration::whereKey($registrationId)->lockForUpdate()->first();

            if ($reg->status === 'cancelled') {
                throw new ApiException('This registration is already cancelled.', 409);
            }
            if (now()->gte($event->cancellation_closes_at)) {
                throw new ApiException('The cancellation window for this event has closed.', 422);
            }

            $wasConfirmed = $reg->status === 'confirmed';
            $reg->update(['status' => 'cancelled', 'active' => null, 'cancelled_at' => now()]);

            if ($wasConfirmed) {
                $this->promoteWaitlist($event);
            }

            return [$reg->fresh(), $event];
        });
    }

    /** Fill free seats from the waitlist, oldest first. Caller must hold the event lock. */
    public function promoteWaitlist(Event $event): int
    {
        $promoted = 0;
        $confirmed = Registration::where('event_id', $event->id)->where('status', 'confirmed')->count();

        while ($confirmed < $event->capacity) {
            $next = Registration::where('event_id', $event->id)->where('status', 'waitlisted')
                ->orderBy('id')->lockForUpdate()->first();
            if (! $next) {
                break;
            }
            $next->update(['status' => 'confirmed']);
            $confirmed++;
            $promoted++;
        }
        return $promoted;
    }
}
