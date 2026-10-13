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
            ->update(['processing_status' => 'processing', 'processing_started_at' => now(), 'processing_error' => null]);
        if (! $claimed) return;

        $ad = InstitutionalAd::findOrFail($id);
        $disk = Storage::disk('local');
        $output = 'institutional-ads/'.Str::uuid().'.mp4';
        try {
            $disk->makeDirectory('institutional-ads');
            $converter = app(ScheduledVideoProcessor::class);
            $converter->transcode($disk->path($ad->source_path), $disk->path($output));
            $duration = $converter->probe($disk->path($output))['duration'];
            $ad->update(['path' => $output, 'duration' => $duration,
                'optimized_bytes' => $disk->size($output), 'processing_status' => 'ready',
                'ready_at' => now(), 'processing_error' => null]);
            $disk->delete($ad->source_path);
            $ad->update(['source_path' => null]);
        } catch (\Throwable $e) {
            $disk->delete($output);
            $ad->update(['processing_status' => 'failed',
                'processing_error' => 'No se pudo convertir el anuncio. Revisa FFmpeg, el espacio disponible y el archivo antes de reintentar.']);
            report($e);
        }
    }
}
