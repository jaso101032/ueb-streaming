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
        $advertisement = $live || $active ? null : $schedule->advertisement();
        $upcoming = collect($items)->filter(fn ($i) => $i['state'] === 'upcoming')->map(fn ($i) => [
            'id' => $i['event']->id, 'title' => $i['event']->titulo, 'starts_at' => $i['starts_at'], 'duration' => $i['duration'],
        ])->values();
        $next = $upcoming->take(5)->values();
        $event = $live?->evento ?? ($active['event'] ?? null);
        $data = $event ? ReactPage::event($event) : null;
        // ReactPage builds the public HLS URL against the streaming proxy (port 8080).
        // Stored legacy URLs may point at Laravel's port and must not replace it.
        $playback = $active;
        if ($playback) {
            unset($playback['event']);
        }

        return response()->json(['mode' => $live ? 'live' : ($active ? 'scheduled' : ($advertisement ? 'advertisement' : 'idle')),
            'event' => $data, 'playback' => $live ? null : ($playback ?? ($advertisement ? [
                'state' => 'playing', 'position' => $advertisement['position'], 'duration' => $advertisement['duration'],
            ] : null)),
            'advertisement' => $advertisement ? [
                'id' => $advertisement['ad']->id, 'title' => $advertisement['ad']->title,
                'mediaUrl' => route('channel.ad.media', $advertisement['ad']),
            ] : null,
            'next' => $next, 'agenda' => $upcoming, 'summary' => $schedule->summary($items), 'server_time' => now()->toIso8601String(),
            'resumeTitle' => $live ? ($active['event']->titulo ?? null) : null,
        ])->header('Cache-Control', 'no-store');
    }
}
