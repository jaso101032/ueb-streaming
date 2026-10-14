<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Support\Facades\DB;

class RecordingController extends Controller
{
    public function show(Evento $evento)
    {
        $path = $evento->transmision?->recordingPath();
        abort_unless($evento->estado === 'finalizado' && $path, 404);
        if (config('streaming.x_accel_media')) {
            return response('', 200, ['X-Accel-Redirect' => '/_protected_media/recordings/'.basename($path),
                'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
        }
        // BinaryFileResponse supports byte ranges for seeking without loading the file in memory.
        return response()->file($path, ['Content-Type' => 'video/mp4',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function state(Evento $evento)
    {
        return response()->json(['state' => $evento->estado,
            'ready' => (bool) $evento->transmision?->recordingPath()])
            ->header('Cache-Control', 'no-store');
    }

    /** The protected download endpoint is deliberately separate from public playback. */
    public function download(Evento $evento)
    {
        $path = $evento->transmision?->recordingPath();
        abort_unless($evento->estado === 'finalizado' && $path, 404);

        return response()->download($path, 'uebtv-'.str($evento->slug ?: $evento->id)->slug().'.mp4', [
            'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retry(Evento $evento)
    {
        DB::transaction(function () use ($evento) {
            $event = Evento::whereKey($evento->id)->lockForUpdate()->firstOrFail();
            $stream = $event->transmision()->lockForUpdate()->first();
            abort_unless($event->estado === 'finalizado' && $stream?->recording_status === 'failed', 409);
            $stream->update(['recording_attempts' => 0, 'recording_status' => 'waiting', 'recording_error' => null]);
        });
        return back()->with('success', 'La grabación se volverá a preparar automáticamente.');
    }
}
