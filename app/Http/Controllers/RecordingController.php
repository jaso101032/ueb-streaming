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
