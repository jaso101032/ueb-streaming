<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\ScheduledVideo;
use App\Models\Transmision;
use App\Services\AdaptiveRenditions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Expediente de repositorio de uno o varios eventos: metadatos, autorización,
 * archivos originales y derivados con tamaño y SHA-256 recalculado, y
 * comparación con la huella registrada al procesarlos. Solo lee; no
 * muestra claves de emisión ni datos personales de espectadores.
 */
class RepositoryRecord extends Command
{
    protected $signature = 'uebtv:expediente {evento* : Identificadores de los eventos} {--json : Salida en JSON}';
    protected $description = 'Muestra metadatos, autorización y huellas SHA-256 de originales y derivados de eventos.';

    public function handle(): int
    {
        $records = [];
        foreach ($this->argument('evento') as $id) {
            $event = Evento::withTrashed()->with(['facultad', 'carrera', 'categoria', 'transmision', 'scheduledVideo'])->find($id);
            if (! $event) {
                $this->error("Evento {$id}: no existe.");

                return self::FAILURE;
            }
            $records[] = $this->record($event);
        }

        if ($this->option('json')) {
            $this->line(json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        foreach ($records as $record) {
            $this->info("Evento {$record['id']} · {$record['titulo']}");
            foreach ($record['metadatos'] as $key => $value) $this->line(str_pad($key, 24).' '.($value ?? '—'));
            $this->line('Autorización: '.$record['autorizacion']['estado']);
            foreach ($record['autorizacion'] as $key => $value) {
                if ($key !== 'estado') $this->line('  '.str_pad($key, 22).' '.($value ?? '—'));
            }
            $this->table(['Tipo', 'Archivo', 'Bytes', 'SHA-256', 'Registrada', 'Coincide'], array_map(fn ($f) => [
                $f['tipo'], $f['archivo'], $f['bytes'] ?? '—', $f['sha256'] ?? '—', $f['sha256_registrada'] ?? '—', $f['coincide'],
            ], $record['archivos']));
        }

        return self::SUCCESS;
    }

    private function record(Evento $event): array
    {
        $stream = $event->transmision;
        $video = $event->scheduledVideo;

        return [
            'id' => $event->id,
            'titulo' => $event->titulo,
            'metadatos' => [
                'descripcion' => $event->descripcion,
                'fecha_inicio' => $event->fecha_inicio?->toIso8601String(),
                'fecha_fin' => $event->fecha_fin?->toIso8601String(),
                'facultad' => $event->facultad?->nombre,
                'carrera' => $event->carrera?->nombre,
                'categoria' => $event->categoria?->nombre,
                'tipo_evento' => $event->tipo_evento,
                'estado' => $event->estado,
                'visibilidad' => $event->visibilidad,
                'licencia' => $event->licencia,
                'requiere_revision' => $event->requiere_aprobacion ? 'sí' : 'no',
                'origen' => $video ? ($video->isCatalogOnly() ? 'archivo cargado (catálogo)' : 'archivo cargado (canal)') : ($stream ? 'grabación del directo' : 'sin medios'),
                'duracion_s' => $video?->duration ?? ($stream?->recording_metadata['durationSeconds'] ?? null),
                'publicacion_grabacion' => $stream?->publicacion,
                'eliminado_en' => $event->deleted_at?->toIso8601String(),
            ],
            'autorizacion' => [
                'estado' => $event->authorizationStatus(),
                'responsable' => $event->autorizacion_responsable,
                'referencia' => $event->autorizacion_referencia,
                'alcance' => $event->autorizacion_alcance,
                'fecha' => $event->autorizacion_fecha?->toDateString(),
                'vigencia' => $event->autorizacion_vigencia?->toDateString(),
            ],
            'archivos' => array_values(array_filter(array_merge(
                $stream ? $this->streamFiles($stream) : [],
                $video ? $this->videoFiles($video) : [],
            ))),
        ];
    }

    private function streamFiles(Transmision $stream): array
    {
        $root = rtrim(config('streaming.recordings_root'), '/');
        $metadata = $stream->recording_metadata ?? [];
        $raw = preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $stream->stream_key ?? '') ? $root.'/raw/'.$stream->stream_key.'.flv' : null;
        $files = [
            $this->file('original FLV (OBS)', $raw, $metadata['rawSha256'] ?? null, 'raw/<clave>.flv'),
            $this->file('derivado MP4', $stream->recordingPath(), $metadata['mp4Sha256'] ?? null),
        ];
        if ($stream->subtitles_path) $files[] = $this->file('subtítulos WebVTT', Storage::disk('local')->path($stream->subtitles_path), null);

        return array_merge($files, $this->renditions($stream));
    }

    private function videoFiles(ScheduledVideo $video): array
    {
        $disk = Storage::disk('local');
        $files = [
            $video->source_path ? $this->file('original cargado', $disk->path($video->source_path), $video->source_sha256)
                : ($video->source_sha256 ? ['tipo' => 'original cargado', 'archivo' => '(no conservado)', 'bytes' => $video->source_bytes, 'sha256' => null, 'sha256_registrada' => $video->source_sha256, 'coincide' => 'no verificable'] : null),
            $video->path ? $this->file('derivado MP4', $disk->path($video->path), $video->output_sha256) : null,
        ];
        if ($video->subtitles_path) $files[] = $this->file('subtítulos WebVTT', $disk->path($video->subtitles_path), null);

        return array_merge($files, $this->renditions($video));
    }

    private function renditions($media): array
    {
        if ($media->renditions_status !== 'ready' || ! AdaptiveRenditions::validDirectory($media->renditions_path)) return [];
        $disk = Storage::disk('local');
        $bytes = 0;
        $count = 0;
        foreach ($disk->allFiles($media->renditions_path) as $file) {
            $bytes += $disk->size($file);
            $count++;
        }
        $master = $disk->path($media->renditions_path.'/master.m3u8');

        return [[
            'tipo' => 'calidades HLS ('.implode(', ', (array) $media->renditions_levels).')',
            'archivo' => basename($media->renditions_path)."/ ({$count} archivos)",
            'bytes' => $bytes,
            'sha256' => is_file($master) ? hash_file('sha256', $master) : null,
            'sha256_registrada' => null,
            'coincide' => 'huella de master.m3u8',
        ]];
    }

    private function file(string $type, ?string $path, ?string $registered, ?string $label = null): ?array
    {
        if (! $path) return null;
        $exists = is_file($path) && ! is_link($path);
        $hash = $exists ? hash_file('sha256', $path) : null;

        return [
            'tipo' => $type,
            'archivo' => $label ?? basename($path),
            'bytes' => $exists ? filesize($path) : null,
            'sha256' => $hash,
            'sha256_registrada' => $registered,
            'coincide' => ! $exists ? 'archivo ausente' : ($registered ? ($registered === $hash ? 'sí' : 'NO') : 'sin huella registrada'),
        ];
    }
}
