<?php

namespace App\Services;

use App\Support\Audit;
use App\Models\Evento;
use App\Models\Transmision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class RecordingProcessor
{
    public function process(int $id): void
    {
        $stream = Transmision::find($id);
        if (!$stream) return;
        if ($stream->recording_status === 'ready') {
            try { $this->cleanupSource($stream); } catch (Throwable $exception) { report($exception); }
            return;
        }
        $claimed = DB::transaction(function () use ($stream) {
            $event = Evento::whereKey($stream->evento_id)->lockForUpdate()->first();
            $stream = Transmision::whereKey($stream->id)->lockForUpdate()->firstOrFail();
            if (!$event || $event->estado === 'cancelado' || $stream->recording_status === 'ready' ||
                $stream->recording_status === 'idle' || ($stream->recording_status === 'failed' && $stream->recording_attempts >= 3)) return null;
            $grace = max(15, config('streaming.reconnect_grace'));
            if ($stream->last_signal_at?->gt(now()->subSeconds($grace))) return null;
            if ($stream->recording_status === 'failed' && $stream->recording_attempt_at?->gt(now()->subMinute())) return null;

            // Close authorization before touching the source: no late reconnects.
            $stream->update([
                'recording_status' => 'processing', 'activo' => false,
                'finalizado_en' => $stream->finalizado_en ?? now(),
                'recording_attempt_at' => now(), 'recording_attempts' => $stream->recording_attempts + 1,
                'recording_error' => null, 'espectadores_activos' => 0,
            ]);
            $event->update(['estado' => 'finalizado']);
            return $stream;
        });
        if (!$claimed) return;

        $temporary = null;
        try {
            if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $claimed->stream_key)) {
                throw new \RuntimeException('Invalid source name');
            }
            $root = rtrim(config('streaming.recordings_root'), '/');
            $source = $root.'/raw/'.$claimed->stream_key.'.flv';
            if (!is_file($source) || is_link($source) || !is_readable($source)) {
                throw new \RuntimeException('Recording source unavailable');
            }
            if (!is_dir($root.'/vod') && !mkdir($root.'/vod', 0770, true) && !is_dir($root.'/vod')) {
                throw new \RuntimeException('Output unavailable');
            }
            $filename = preg_match('/^[a-f0-9-]{36}\.mp4$/D', $claimed->recording_file ?? '')
                ? $claimed->recording_file : Str::uuid().'.mp4';
            $claimed->update(['recording_file' => $filename]);
            $temporary = $root.'/vod/.'.$filename;
            $process = new Process(['/usr/bin/python3', base_path('scripts/prepare-recording.py'),
                $source, $temporary, config('streaming.ffmpeg'), config('streaming.ffprobe'),
                (string) ($claimed->recording_fps_limit ?: 'source')]);
            $process->setTimeout(21600);
            $process->run();
            if ($process->getExitCode() === 75) {
                $claimed->update(['recording_status' => 'waiting', 'recording_attempts' => max(0, $claimed->recording_attempts - 1), 'last_signal_at' => now()]);
                return;
            }
            if (!$process->isSuccessful() || !is_file($temporary) || filesize($temporary) === 0) {
                throw new \RuntimeException('Conversion failed');
            }
            $metadata = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($metadata) || ($metadata['mp4Bytes'] ?? 0) <= 0 || ($metadata['rawBytes'] ?? 0) <= 0) {
                throw new \RuntimeException('Invalid recording metadata');
            }
            chmod($temporary, 0640);
            if (!rename($temporary, $root.'/vod/'.$filename)) throw new \RuntimeException('Publish failed');
            // File is served through a guarded route; .mp4 alone never exposes it.
            // Keep the FLV until the validated MP4 is durably marked ready. A
            // pending marker lets the worker retry cleanup after a crash or a
            // temporary filesystem error without re-encoding the video.
            $metadata['rawCleanupPending'] = true;
            // By default the recording is published automatically. Events marked
            // for editorial review wait until an administrator approves them.
            $review = (bool) $claimed->evento?->requiere_aprobacion;
            $claimed->update(['recording_status' => 'ready', 'recording_file' => $filename,
                'recording_metadata' => $metadata, 'publicacion' => $review ? 'pendiente' : 'aprobada',
                'publicacion_revisada_por' => null, 'publicacion_revisada_en' => null,
                'recording_ready_at' => now(), 'recording_error' => null]);
            Audit::record($review ? 'grabacion.pendiente_revision' : 'grabacion.publicada_automaticamente', $claimed->evento, [], 'Sistema');
            if (! $review) {
                try { app(AudienceAnnouncements::class)->publish($claimed->evento, 'video'); }
                catch (Throwable $exception) { report($exception); }
            }
            try { $this->cleanupSource($claimed); } catch (Throwable $exception) { report($exception); }
        } catch (Throwable $exception) {
            if ($temporary && is_file($temporary)) unlink($temporary);
            $claimed->update(['recording_status' => 'failed',
                'recording_error' => 'No se pudo preparar el video. Revisa el archivo original, el espacio disponible y FFmpeg.']);
        }
    }

    private function cleanupSource(Transmision $stream): void
    {
        $metadata = $stream->recording_metadata ?? [];
        if (! ($metadata['rawCleanupPending'] ?? false) || ! $stream->recordingPath() ||
            ! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $stream->stream_key ?? '')) return;

        $source = rtrim(config('streaming.recordings_root'), '/').'/raw/'.$stream->stream_key.'.flv';
        if (is_link($source)) return;
        if (config('streaming.keep_sources')) {
            // The OBS original stays in place (OBS can no longer publish to this
            // key once the recording is ready) with fingerprints for integrity checks.
            $metadata['rawCleanupPending'] = false;
            $metadata['rawRetained'] = is_file($source);
            if (is_file($source)) $metadata['rawSha256'] = hash_file('sha256', $source) ?: null;
            $metadata['mp4Sha256'] = hash_file('sha256', $stream->recordingPath()) ?: null;
            $stream->update(['recording_metadata' => $metadata]);

            return;
        }
        if (is_file($source) && ! @unlink($source)) return;
        clearstatcache(true, $source);
        if (file_exists($source) || is_link($source)) return;

        $metadata['rawCleanupPending'] = false;
        $metadata['rawRemovedAt'] = now()->toIso8601String();
        $stream->update(['recording_metadata' => $metadata]);
    }
}
