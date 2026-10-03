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

    public function process(int $id): void
    {
        $claimed = ScheduledVideo::whereKey($id)->where('processing_status', 'pending')
            ->update(['processing_status' => 'processing', 'processing_started_at' => now(), 'processing_error' => null]);
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
            $process = new Process([config('streaming.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
                '-protocol_whitelist', 'file', '-i', $disk->path($video->source_path),
                '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn', '-map_metadata', '-1',
                '-vf', "scale=w='min(1920,iw)':h='min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1",
                '-c:v', 'libx264', '-preset', 'fast', '-crf', '23', '-pix_fmt', 'yuv420p', '-threads', '2',
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-movflags', '+faststart', $disk->path($output)]);
            $process->setTimeout(3600)->mustRun();
            $metadata = $this->probe($disk->path($output));
            $video->update(['path' => $output, 'duration' => $metadata['duration'], 'optimized_bytes' => $disk->size($output),
                'processing_status' => 'ready', 'ready_at' => now(), 'processing_error' => null]);
            $disk->delete($video->source_path);
            $video->update(['source_path' => null]);
        } catch (\Throwable $e) {
            $disk->delete($output);
            $video->update(['processing_status' => 'failed', 'processing_error' => 'No se pudo convertir el video. Comprueba FFmpeg, el espacio disponible y el archivo antes de reintentar.']);
            report($e);
        }
    }
}
