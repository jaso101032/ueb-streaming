<?php

namespace App\Http\Controllers;

use App\Models\Transmision;
use Illuminate\Http\Request;

/**
 * Entrega el directo HLS sin exponer la clave privada de OBS.
 *
 * Nginx-RTMP sigue escribiendo los archivos con el nombre de la clave. El
 * espectador solo conoce el identificador público: Laravel comprueba el permiso
 * del evento, reescribe el manifiesto y entrega cada segmento mediante una
 * ubicación interna de Nginx (o directamente en desarrollo).
 */
class LivePlaybackController extends Controller
{
    public function playlist(Request $request, string $playbackId)
    {
        $stream = $this->authorizedStream($request, $playbackId);
        $path = $this->hlsPath($stream->stream_key.'.m3u8');
        abort_unless($path && is_readable($path), 404);
        $manifest = @file_get_contents($path);
        abort_unless(is_string($manifest) && str_starts_with($manifest, '#EXTM3U'), 404);

        $prefix = preg_quote($stream->stream_key, '/');
        $rewritten = preg_replace_callback('/^'.$prefix.'-(\d+)\.ts$/m',
            fn ($m) => $playbackId.'/'.$m[1].'.ts', $manifest);
        // Una línea que todavía contenga la clave nunca sale del servidor.
        abort_if(str_contains($rewritten, $stream->stream_key), 404);

        return response($rewritten, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function segment(Request $request, string $playbackId, string $sequence)
    {
        $stream = $this->authorizedStream($request, $playbackId);
        $name = $stream->stream_key.'-'.$sequence.'.ts';
        $path = $this->hlsPath($name);
        abort_unless($path && is_readable($path), 404);
        $headers = ['Content-Type' => 'video/mp2t', 'Cache-Control' => 'private, max-age=60', 'X-Content-Type-Options' => 'nosniff'];
        if (config('streaming.x_accel_media')) {
            return response('', 200, $headers + ['X-Accel-Redirect' => '/_protected_hls/'.$name]);
        }

        return response()->file($path, $headers);
    }

    private function authorizedStream(Request $request, string $playbackId): Transmision
    {
        $stream = Transmision::with('evento')->where('playback_id', $playbackId)->first();
        abort_unless($stream && $stream->evento && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', (string) $stream->stream_key), 404);
        // Un recurso no autorizado responde como inexistente: no revela que existe.
        abort_unless($stream->evento->isVisibleTo($request->user()) && $stream->evento->estado !== 'cancelado', 404);

        return $stream;
    }

    private function hlsPath(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}(-\d{1,12}\.ts|\.m3u8)$/D', $name)) return null;
        $path = rtrim(config('streaming.hls_root'), '/').'/'.$name;

        return is_file($path) && ! is_link($path) ? $path : null;
    }
}
