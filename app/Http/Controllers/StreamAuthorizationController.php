<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Transmision;
use App\Services\StreamCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StreamAuthorizationController extends Controller
{
    public function __invoke(Request $request, StreamCallback $callback)
    {
        $callback->verify($request);
        $stream = Transmision::where('stream_key', $request->input('name'))->first();
        abort_unless($stream, 403);

        DB::transaction(function () use ($request, $stream) {
            // Same lock order as the finish action and recording worker.
            $event = Evento::whereKey($stream->evento_id)->lockForUpdate()->first();
            $stream = Transmision::whereKey($stream->id)->lockForUpdate()->firstOrFail();
            abort_unless($event && in_array($event->estado, ['programado', 'en_vivo']) &&
                !$stream->finalizado_en && !in_array($stream->recording_status, ['processing', 'ready', 'failed']), 403);

            $call = $request->input('call');
            if (!in_array($call, ['publish', 'update_publish'])) return;
            $publisher = $request->input('clientid');
            abort_unless(is_string($publisher) && preg_match('/^\d{1,100}$/D', $publisher), 403);
            if ($call === 'update_publish' && $stream->publisher_id !== $publisher) abort(403);
            // A second publisher cannot take over a still-active session.
            if ($call === 'publish' && $stream->activo && $stream->publisher_id &&
                $stream->publisher_id !== $publisher && $stream->last_signal_at?->gt(now()->subSeconds(15))) abort(409);

            app(\App\Services\ChannelClock::class)->signal($stream);
            $stream->update([
                'activo' => true, 'publisher_id' => $publisher, 'last_signal_at' => now(),
                'iniciado_en' => $stream->iniciado_en ?? now(), 'recording_status' => 'recording',
            ]);
            if ($event->estado !== 'en_vivo') $event->update(['estado' => 'en_vivo']);
        });

        return response('', 204);
    }

    public function disconnected(Request $request, StreamCallback $callback)
    {
        $callback->verify($request);
        // An old disconnect delivered after reconnect must not end the new session.
        DB::transaction(function () use ($request) {
            $stream = Transmision::where('stream_key', $request->input('name'))
                ->where('publisher_id', (string) $request->input('clientid', ''))
                ->whereIn('recording_status', ['recording', 'waiting'])->lockForUpdate()->first();
            if (!$stream) return;
            app(\App\Services\ChannelClock::class)->stop($stream);
            $stream->update(['activo' => false, 'last_signal_at' => now(), 'recording_status' => 'waiting']);
        });

        return response('', 204);
    }
}
