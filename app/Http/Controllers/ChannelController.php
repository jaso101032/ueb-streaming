<?php

namespace App\Http\Controllers;

use App\Services\ChannelSchedule;
use App\Support\ReactPage;

class ChannelController extends Controller
{
    public function index()
    {
        return view('channel');
    }

    public function state(ChannelSchedule $schedule)
    {
        $items = $schedule->timeline();
        $live = $schedule->live();
        $active = collect($items)->first(fn ($i) => in_array($i['state'], ['playing', 'paused']));
        $next = collect($items)->filter(fn ($i) => $i['state'] === 'upcoming')->take(5)->map(fn ($i) => [
            'id' => $i['event']->id, 'title' => $i['event']->titulo, 'starts_at' => $i['starts_at'], 'duration' => $i['duration'],
        ])->values();
        $event = $live?->evento ?? ($active['event'] ?? null);
        $data = $event ? ReactPage::event($event) : null;
        // ReactPage builds the public HLS URL against the streaming proxy (port 8080).
        // Stored legacy URLs may point at Laravel's port and must not replace it.
        $playback = $active;
        if ($playback) {
            unset($playback['event']);
        }

        return response()->json(['mode' => $live ? 'live' : ($active ? 'scheduled' : 'idle'),
            'event' => $data, 'playback' => $live ? null : $playback,
            'next' => $next, 'summary' => $schedule->summary($items), 'server_time' => now()->toIso8601String(),
            'resumeTitle' => $live ? ($active['event']->titulo ?? null) : null,
        ])->header('Cache-Control', 'no-store');
    }
}
