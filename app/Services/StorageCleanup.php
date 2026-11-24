<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\Transmision;
use App\Support\Audit;
use App\Support\Subtitles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Eliminar contenido y liberar espacio. Eliminar es borrar todo: el evento
 * (o la publicidad) con su video, la grabación original, las calidades y los
 * subtítulos. Se usa en «Eliminar» de Eventos y en «Liberar espacio».
 *
 * Nunca se borra lo que está en vivo, grabándose o procesándose; en «Liberar
 * espacio» tampoco se sugieren los programas por emitir ni la publicidad activa.
 */
class StorageCleanup
{
    /** Vistas en este período para considerar un video "poco visto". */
    public const VIEWS_DAYS = 60;

    public const FEW_VIEWS = 10;

    public function __construct(private ChannelSchedule $schedule) {}

    /** Todo lo que se puede eliminar, con el peso real de sus archivos. */
    public function candidates(): array
    {
        $timeline = $this->schedule->timeline();
        $views = DB::table('event_video_views')->where('viewed_on', '>=', now()->subDays(self::VIEWS_DAYS)->toDateString())
            ->selectRaw('evento_id, count(*) as total')->groupBy('evento_id')->pluck('total', 'evento_id');
        $items = collect();

        foreach (Evento::withTrashed()->with(['transmision', 'scheduledVideo', 'categoria'])->get() as $event) {
            if ($this->inUse($event)) continue;
            // Programas de la parrilla por emitir o al aire: todavía hacen falta.
            if ($event->scheduledVideo && ! $event->trashed()
                && in_array($timeline[$event->id]['state'] ?? null, ['upcoming', 'playing', 'paused'], true)) continue;
            $bytes = $this->eventBytes($event);
            if ($bytes <= 0) continue;
            $items->push(['key' => 'event:'.$event->id, 'title' => $event->titulo,
                'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                'category' => $event->categoria?->nombre ?? 'Institucional', 'views' => (int) ($views[$event->id] ?? 0),
                'date' => ($event->fecha_inicio ?? $event->created_at)?->toDateString(),
                'type' => $event->scheduledVideo ? ($event->scheduledVideo->isCatalogOnly() ? 'catalog' : 'scheduled') : 'recording', 'bytes' => $bytes,
                'deleted' => $event->trashed(),
                'downloadUrl' => \App\Http\Controllers\Admin\MediaDownloadController::eventFile($event) ? route('admin.media.download', $event->id) : null]);
        }

        foreach (InstitutionalAd::where('is_active', false)->whereNotIn('processing_status', ['pending', 'processing'])->get() as $ad) {
            $bytes = $this->sizes([$ad->path, $ad->source_path, $ad->subtitles_path]);
            if ($bytes > 0) $items->push(['key' => 'ad:'.$ad->id, 'type' => 'ad', 'title' => $ad->title, 'image' => null,
                'category' => 'Publicidad desactivada', 'views' => null, 'deleted' => false, 'bytes' => $bytes,
                'date' => $ad->created_at->toDateString(),
                'downloadUrl' => $ad->processing_status === 'ready' && $ad->path && Storage::disk('local')->exists($ad->path) ? route('admin.media.ad-download', $ad) : null]);
        }

        return $this->tag($items)->sortByDesc('bytes')->values()->all();
    }

    /**
     * Elimina lo elegido. Solo se aceptan elementos que siguen siendo
     * eliminables en este momento. Devuelve los bytes liberados y lo omitido.
     */
    public function apply(array $keys): array
    {
        $valid = collect($this->candidates())->keyBy('key');
        $freed = 0;
        $done = 0;
        foreach (array_unique($keys) as $key) {
            $item = $valid[$key] ?? null;
            if (! $item) continue;
            [$type, $id] = array_pad(explode(':', $key), 2, null);
            $ok = match ($type) {
                'event' => $this->purgeEvent(Evento::withTrashed()->find($id)),
                'ad' => $this->removeAd(InstitutionalAd::find($id)),
                default => false,
            };
            if ($ok) { $freed += $item['bytes']; $done++; }
        }
        \App\Support\StorageUsage::flush();

        return ['freed' => $freed, 'done' => $done, 'skipped' => count(array_unique($keys)) - $done];
    }

    /**
     * Por qué no se puede eliminar ahora un evento, o null si se puede:
     * está en vivo, grabándose o se están preparando sus archivos.
     */
    public function inUse(Evento $event): ?string
    {
        $stream = $event->transmision;
        if ($event->estado === 'en_vivo' || ($stream && $stream->activo && ! $stream->finalizado_en)) {
            return 'El evento está en vivo. Finaliza la transmisión antes de eliminarlo.';
        }
        if ($stream && in_array($stream->recording_status, ['recording', 'waiting', 'processing'], true)) {
            return 'Se está preparando la grabación. Inténtalo de nuevo cuando termine.';
        }
        $video = $event->scheduledVideo;
        if ($video && in_array($video->processing_status, ['pending', 'processing'], true)) {
            return 'Se está preparando el video. Inténtalo de nuevo cuando termine.';
        }
        foreach (array_filter([$stream, $video]) as $media) {
            if ($media->renditions_status === 'processing' || $media->subtitles_status === 'processing') {
                return 'Se están preparando las calidades o los subtítulos. Inténtalo de nuevo en unos minutos.';
            }
        }

        return null;
    }

    /**
     * Borra de forma definitiva los archivos de un evento. Si es un video de la
     * parrilla, el registro se conserva (eliminado) para que su franja ya emitida
     * siga ocupando su lugar y el horario del canal no se corra.
     */
    public function purgeEvent(?Evento $event): bool
    {
        if (! $event) return false;
        $bytes = $this->eventBytes($event);
        $title = $event->titulo;
        $stream = $event->transmision;
        $video = $event->scheduledVideo;
        // Qué borrar del disco, calculado antes de tocar la base de datos.
        $files = array_filter([$stream ? $this->rawFile($stream) : null, $stream ? $this->vodFile($stream) : null]);
        $stored = array_filter([$video?->path, $video?->source_path]);
        $subtitles = array_filter([$stream?->subtitles_path, $video?->subtitles_path]);
        $renditions = array_filter([$stream, $video], fn ($m) => $m && AdaptiveRenditions::validDirectory($m->renditions_path));
        $renditionDirs = array_map(fn ($m) => $m->renditions_path, $renditions);
        $thumbnail = ! $video && $event->miniatura && Evento::withTrashed()->where('miniatura', $event->miniatura)->count() === 1
            ? $event->miniatura : null;

        // Primero la base de datos (todo o nada); después los archivos.
        DB::transaction(function () use ($event, $video, $title, $bytes) {
            Audit::record('evento.eliminado', $event, ['titulo' => $title, 'liberado' => self::human($bytes),
                // Huellas de lo que se borra: permiten verificar después qué archivos existían.
                'huellas' => array_filter([
                    'grabacion_original' => $event->transmision?->recording_metadata['rawSha256'] ?? null,
                    'grabacion_mp4' => $event->transmision?->recording_metadata['mp4Sha256'] ?? null,
                    'video_original' => $video?->source_sha256,
                    'video_publicado' => $video?->output_sha256,
                ])]);
            if ($video) {
                // La franja ya emitida conserva su lugar: el registro se queda
                // (eliminado) sin archivos.
                $video->update(['source_path' => null, 'renditions_path' => null, 'renditions_status' => 'removed',
                    'renditions_levels' => null, 'subtitles_path' => null]);
                if (! $event->trashed()) $event->delete();
            } else {
                $event->forceDelete();
            }
        });

        foreach ($files as $file) @unlink($file);
        $disk = Storage::disk('local');
        $disk->delete($stored);
        foreach ($renditionDirs as $dir) $disk->deleteDirectory($dir);
        foreach ($subtitles as $path) Subtitles::delete($path);
        if ($thumbnail) Storage::disk('public')->delete($thumbnail);

        return true;
    }

    public function removeAd(?InstitutionalAd $ad): bool
    {
        if (! $ad || $ad->is_active || in_array($ad->processing_status, ['pending', 'processing'], true)) return false;
        $bytes = $this->sizes([$ad->path, $ad->source_path, $ad->subtitles_path]);
        $files = array_filter([$ad->path, $ad->source_path]);
        $subtitles = $ad->subtitles_path;
        Audit::record('publicidad.eliminada', $ad, ['titulo' => $ad->title, 'liberado' => self::human($bytes)]);
        $ad->delete();
        Storage::disk('local')->delete($files);
        Subtitles::delete($subtitles);

        return true;
    }

    private function tag(Collection $items): Collection
    {
        $heavy = $items->sortByDesc('bytes')->take(3)->pluck('key')->all();

        return $items->map(function ($item) use ($heavy) {
            $tags = [];
            if ($item['deleted']) $tags[] = 'Ya eliminado';
            if (in_array($item['key'], $heavy, true) && $item['bytes'] >= 100 * 1024 ** 2) $tags[] = 'Más pesado';
            if ($item['views'] !== null && $item['views'] < self::FEW_VIEWS) $tags[] = 'Pocas vistas';
            if ($item['type'] === 'ad') $tags[] = 'Desactivada';

            return $item + ['tags' => $tags, 'size' => self::human($item['bytes'])];
        });
    }

    public function eventBytes(Evento $event): int
    {
        $bytes = 0;
        if ($stream = $event->transmision) {
            foreach (array_filter([$this->rawFile($stream), $this->vodFile($stream)]) as $file) $bytes += (int) filesize($file);
            $bytes += $this->renditionsBytes($stream) + $this->sizes([$stream->subtitles_path]);
        }
        if ($video = $event->scheduledVideo) {
            $bytes += $this->sizes([$video->path, $video->source_path, $video->subtitles_path]) + $this->renditionsBytes($video);
        }

        return $bytes;
    }

    private function renditionsBytes(object $media): int
    {
        if (! AdaptiveRenditions::validDirectory($media->renditions_path)) return 0;
        $disk = Storage::disk('local');

        return $disk->exists($media->renditions_path) ? (int) collect($disk->allFiles($media->renditions_path))->sum(fn ($f) => $disk->size($f)) : 0;
    }

    private function sizes(array $paths): int
    {
        $disk = Storage::disk('local');

        return (int) collect(array_filter($paths))->sum(fn ($path) => $disk->exists($path) ? $disk->size($path) : 0);
    }

    private function rawFile(Transmision $stream): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $stream->stream_key ?? '')) return null;
        $raw = rtrim(config('streaming.recordings_root'), '/').'/raw/'.$stream->stream_key.'.flv';

        return is_file($raw) && ! is_link($raw) ? $raw : null;
    }

    private function vodFile(Transmision $stream): ?string
    {
        if (! preg_match('/^[a-f0-9-]{36}\.mp4$/D', $stream->recording_file ?? '')) return null;
        $file = rtrim(config('streaming.recordings_root'), '/').'/vod/'.$stream->recording_file;

        return is_file($file) && ! is_link($file) ? $file : null;
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1024 ** 2 ? \App\Support\UploadLimits::human($bytes) : max(1, (int) round($bytes / 1024)).' KB';
    }
}
