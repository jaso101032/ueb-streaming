<?php

namespace App\Services;

use App\Models\InstitutionalAd;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstitutionalAdProcessor
{
    public function process(int $id): void
    {
        $claimed = InstitutionalAd::whereKey($id)->where('processing_status', 'pending')
            ->update(['processing_status' => 'processing', 'processing_started_at' => now(),
                'processing_heartbeat_at' => now(), 'processing_progress' => 0, 'processing_error' => null]);
        if (! $claimed) return;

        $ad = InstitutionalAd::findOrFail($id);
        $disk = Storage::disk('local');
        $output = 'institutional-ads/'.Str::uuid().'.mp4';
        try {
            $disk->makeDirectory('institutional-ads');
            $converter = app(ScheduledVideoProcessor::class);
            $converter->transcode($disk->path($ad->source_path), $disk->path($output), $ad->duration,
                fn (int $percent) => InstitutionalAd::whereKey($id)->where('processing_status', 'processing')
                    ->update(['processing_progress' => $percent, 'processing_heartbeat_at' => now()]));
            $duration = $converter->probe($disk->path($output))['duration'];
            $ad->update(['path' => $output, 'duration' => $duration,
                'optimized_bytes' => $disk->size($output), 'processing_status' => 'ready',
                'processing_progress' => 100, 'processing_heartbeat_at' => now(),
                'ready_at' => now(), 'processing_error' => null]);
            $hashes = ['output_sha256' => hash_file('sha256', $disk->path($output)) ?: null];
            if (config('streaming.keep_sources')) {
                $hashes['source_sha256'] = hash_file('sha256', $disk->path($ad->source_path)) ?: null;
                $ad->update($hashes);
            } else {
                $disk->delete($ad->source_path);
                $ad->update($hashes + ['source_path' => null]);
            }
        } catch (\Throwable $e) {
            $disk->delete($output);
            $ad->update(['processing_status' => 'failed',
                'processing_error' => 'No se pudo convertir el anuncio. Revisa FFmpeg, el espacio disponible y el archivo antes de reintentar.']);
            report($e);
        }
    }
}
