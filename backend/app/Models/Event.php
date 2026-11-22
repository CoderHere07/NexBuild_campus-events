<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'organizer_id', 'category_id', 'title', 'description', 'location',
        'starts_at', 'registration_closes_at', 'cancellation_closes_at', 'capacity', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'cancellation_closes_at' => 'datetime',
        'capacity' => 'integer',
    ];

    public function category() { return $this->belongsTo(EventCategory::class, 'category_id'); }
    public function organizer() { return $this->belongsTo(User::class, 'organizer_id'); }
    public function registrations() { return $this->hasMany(Registration::class); }

    /** Adds confirmed_count and waitlist_count columns. */
    public function scopeWithSeatCounts(Builder $q): Builder
    {
        return $q->withCount([
            'registrations as confirmed_count' => fn ($r) => $r->where('status', 'confirmed'),
            'registrations as waitlist_count' => fn ($r) => $r->where('status', 'waitlisted'),
        ]);
    }

    public function present(?Registration $mine = null): array
    {
        $confirmed = $this->getAttribute('confirmed_count')
            ?? $this->registrations()->where('status', 'confirmed')->count();
        $waitlist = $this->getAttribute('waitlist_count')
            ?? $this->registrations()->where('status', 'waitlisted')->count();
        $seats = max(0, $this->capacity - $confirmed);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null,
            'category_id' => $this->category_id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at->toIso8601String(),
            'cancellation_closes_at' => $this->cancellation_closes_at->toIso8601String(),
            'capacity' => $this->capacity,
            'status' => $this->status,
            'confirmed_count' => (int) $confirmed,
            'seats_available' => $seats,
            'waitlist_count' => (int) $waitlist,
            'is_full' => $seats === 0,
            'registration_open' => $this->status === 'published' && now()->lt($this->registration_closes_at),
            'my_registration' => $mine?->present($this),
        ];
    }
}
