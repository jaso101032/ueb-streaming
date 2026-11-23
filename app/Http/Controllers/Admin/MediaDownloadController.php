<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Support\Audit;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga del video de un evento (grabación de directo o video de la
 * parrilla) o de una publicidad, para guardarlo fuera de la plataforma.
 * También sirve para eventos ya eliminados: así se puede guardar una copia
 * antes de borrar sus archivos en «Liberar espacio».
 */
class MediaDownloadController extends Controller
{
    public function event(int $evento)
    {
        $event = Evento::withTrashed()->with(['transmision', 'scheduledVideo'])->findOrFail($evento);
        $path = self::eventFile($event);
        abort_unless($path, 404);
        Audit::record('video.descargado', $event);

        return $this->send($path, $event->slug ?: $event->titulo ?: (string) $event->id);
    }

    public function ad(InstitutionalAd $ad)
    {
        $disk = Storage::disk('local');
        abort_unless($ad->processing_status === 'ready' && $ad->path && $disk->exists($ad->path), 404);
        Audit::record('video.descargado', $ad);

        return $this->send($disk->path($ad->path), 'publicidad-'.$ad->title);
    }

    /** Archivo MP4 descargable de un evento, o null si no tiene. */
    public static function eventFile(Evento $event): ?string
    {
        if ($video = $event->scheduledVideo) {
            $disk = Storage::disk('local');

            return $video->processing_status === 'ready' && $video->path && $disk->exists($video->path) ? $disk->path($video->path) : null;
        }
        $stream = $event->transmision;
        if (! $stream || ! in_array($event->estado, ['finalizado', 'cancelado'], true)) return null;

        return $stream->recordingPath();
    }

    private function send(string $path, string $name)
    {
        return response()->download($path, 'uebtv-'.(str($name)->slug()->limit(80, '') ?: 'video').'.mp4', [
            'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
