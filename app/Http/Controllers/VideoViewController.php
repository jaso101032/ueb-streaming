<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoViewController extends Controller
{
    public function store(Request $request, Evento $evento)
    {
        $data = $request->validate(['viewer' => ['required', 'uuid']]);
        $evento->loadMissing(['scheduledVideo', 'transmision']);

        $scheduled = $evento->scheduledVideo;
        $scheduledPlayable = $scheduled && in_array($scheduled->playback()['state'], ['playing', 'ended'], true);
        $recordingPlayable = $evento->estado !== 'en_vivo' &&
            ($evento->video_url || ($evento->estado === 'finalizado' && $evento->transmision?->recordingPath()));
        abort_unless($evento->estado !== 'cancelado' && ($scheduledPlayable || $recordingPlayable), 409);

        DB::table('event_video_views')->insertOrIgnore([
            'evento_id' => $evento->id,
            'viewer_key' => $data['viewer'],
            'viewed_on' => now()->toDateString(),
            'created_at' => now(),
        ]);

        return response()->json([
            'views' => DB::table('event_video_views')->where('evento_id', $evento->id)->count(),
        ])->header('Cache-Control', 'no-store');
    }
}
