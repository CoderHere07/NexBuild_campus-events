<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Registration;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function categories()
    {
        return EventCategory::orderBy('name')->get(['id', 'name', 'slug']);
    }

    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);

        $q = Event::with('category')->withSeatCounts()
            ->where('status', 'published')
            ->where('starts_at', '>=', now());

        if ($term = $r->query('q')) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('location', 'like', $like));
        }
        if ($cat = $r->query('category')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $cat));
        }

        $page = $q->orderBy('starts_at')->paginate(9);
        $page->getCollection()->transform(fn (Event $e) => $e->present());

        return $page;
    }

    public function show(Request $r, int $id)
    {
        $event = Event::with('category')->withSeatCounts()->findOrFail($id);
        $user = $r->user('sanctum');

        // Drafts are invisible to everyone except their own organizer.
        if ($event->status === 'draft' && (! $user || $user->id !== $event->organizer_id)) {
            abort(404, 'Event not found.');
        }

        $mine = $user
            ? Registration::where('event_id', $event->id)->where('user_id', $user->id)->latest('id')->first()
            : null;

        return $event->present($mine);
    }
}
