<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\AdaptiveRenditions;
use Illuminate\Http\Request;

/**
 * Entrega las listas y segmentos HLS de las calidades adaptativas con los
 * mismos permisos que el video al que pertenecen.
 */
class RenditionController extends Controller
{
    public function show(Request $request, Evento $evento, string $file)
    {
        if ($scheduled = $evento->scheduledVideo) {
            abort_unless($evento->estado !== 'cancelado' && in_array($scheduled->playback()['state'], ['playing', 'paused', 'ended'], true), 404);
            $media = $scheduled;
        } else {
            abort_unless(RecordingController::playableRecording($evento, $request->user()), 404);
            $media = $evento->transmision;
        }
        $path = AdaptiveRenditions::file($media, $file);
        abort_unless($path, 404);

        $playlist = str_ends_with($file, '.m3u8');
        $headers = ['Content-Type' => $playlist ? 'application/vnd.apple.mpegurl' : 'video/mp2t',
            // Las listas se vuelven a pedir en cada visita; los segmentos no cambian.
            'Cache-Control' => $playlist ? 'private, no-cache' : 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff'];
        if (config('streaming.x_accel_media')) {
            $relative = substr($media->renditions_path, strlen(AdaptiveRenditions::ROOT) + 1).'/'.$file;

            return response('', 200, $headers + ['X-Accel-Redirect' => '/_protected_media/renditions/'.$relative]);
        }

        return response()->file($path, $headers);
    }
}
