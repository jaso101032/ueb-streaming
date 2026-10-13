<?php

namespace App\Services;

use App\Models\ScheduledVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ScheduledVideoProcessor
{
    public function probe(string $path): array
    {
        $p = new Process([config('streaming.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file', '-show_entries', 'format=duration:stream=codec_type,codec_name,width,height', '-of', 'json', $path]);
        $p->setTimeout(30)->mustRun();
        $info = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $duration = (float) ($info['format']['duration'] ?? 0);
        $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        if (! $video || ! is_finite($duration) || $duration < 1 || $duration > 43200) {
            throw new \RuntimeException('Video inválido o duración fuera de rango.');
        }

        return ['duration' => (int) ceil($duration)];
    }

    public function transcode(string $source, string $output, int $duration, ?callable $onProgress = null): void
    {
        $process = new Process([config('streaming.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-nostats',
            '-stats_period', '1', '-progress', 'pipe:1', '-y',
            '-protocol_whitelist', 'file', '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn', '-map_metadata', '-1',
            '-vf', "scale=w='min(1920,iw)':h='min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1",
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '23', '-pix_fmt', 'yuv420p', '-threads', '2',
            '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-movflags', '+faststart', $output]);
        // Long videos may take longer than real time on a small server. The
        // heartbeat below prevents a healthy job from being reclaimed.
        $process->setTimeout(max(3600, min(86400, $duration * 3)));
        $buffer = '';
        $lastHeartbeat = 0.0;
        $lastPercent = -1;
        $process->run(function (string $type, string $chunk) use (&$buffer, &$lastHeartbeat, &$lastPercent, $duration, $onProgress): void {
            if ($type !== Process::OUT || ! $onProgress) return;
            $buffer .= $chunk;
            while (($end = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $end));
                $buffer = substr($buffer, $end + 1);
                if (str_starts_with($line, 'out_time=')) {
                    $parts = explode(':', substr($line, 9));
                    if (count($parts) === 3) {
                        $seconds = (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (float) $parts[2];
                        $lastPercent = max($lastPercent, min(99, (int) floor($seconds / max(1, $duration) * 100)));
                    }
                }
                if ($line === 'progress=continue' && microtime(true) - $lastHeartbeat >= 5) {
                    $onProgress(max(0, $lastPercent));
                    $lastHeartbeat = microtime(true);
                }
            }
        });
        if (! $process->isSuccessful()) throw new \RuntimeException('FFmpeg no pudo completar la conversión: '.$process->getErrorOutput());
    }

    public function process(int $id): void
    {
        $claimed = ScheduledVideo::whereKey($id)->where('processing_status', 'pending')
            ->update(['processing_status' => 'processing', 'processing_started_at' => now(),
                'processing_heartbeat_at' => now(), 'processing_progress' => 0, 'processing_error' => null]);
        if (! $claimed) {
            return;
        }
        $video = ScheduledVideo::findOrFail($id);
        $disk = Storage::disk('local');
        $output = 'scheduled-videos/'.Str::uuid().'.mp4';
        try {
            if (! $video->evento || $video->evento->estado === 'cancelado') {
                throw new \RuntimeException('Evento no disponible.');
            }
            $disk->makeDirectory('scheduled-videos');
            $this->transcode($disk->path($video->source_path), $disk->path($output), $video->duration,
                fn (int $percent) => ScheduledVideo::whereKey($id)->where('processing_status', 'processing')
                    ->update(['processing_progress' => $percent, 'processing_heartbeat_at' => now()]));
            $metadata = $this->probe($disk->path($output));
            $video->update(['path' => $output, 'duration' => $metadata['duration'], 'optimized_bytes' => $disk->size($output),
                'processing_status' => 'ready', 'processing_progress' => 100, 'processing_heartbeat_at' => now(),
                'ready_at' => now(), 'processing_error' => null]);
            $disk->delete($video->source_path);
            $video->update(['source_path' => null]);
        } catch (\Throwable $e) {
            $disk->delete($output);
            $video->update(['processing_status' => 'failed', 'processing_error' => 'No se pudo convertir el video. Comprueba FFmpeg, el espacio disponible y el archivo antes de reintentar.']);
            report($e);
        }
    }
}
