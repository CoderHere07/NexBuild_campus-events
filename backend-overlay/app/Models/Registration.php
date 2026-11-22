<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $table = 'event_registrations';
    protected $fillable = ['event_id', 'user_id', 'status', 'active', 'cancelled_at'];
    protected $casts = ['cancelled_at' => 'datetime'];

    public function event() { return $this->belongsTo(Event::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function attendance() { return $this->hasOne(EventAttendance::class, 'registration_id'); }

    public function present(Event $event): array
    {
        $position = $this->status === 'waitlisted'
            ? static::where('event_id', $this->event_id)->where('status', 'waitlisted')->where('id', '<=', $this->id)->count()
            : null;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'waitlist_position' => $position,
            'registered_at' => $this->created_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'can_cancel' => $this->status !== 'cancelled' && now()->lt($event->cancellation_closes_at),
            'cancellation_closes_at' => $event->cancellation_closes_at->toIso8601String(),
        ];
    }
}
