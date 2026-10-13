<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scheduled:process {--watch : Mantener el procesador activo}', function () {
    do {
        // Reclaim only jobs without a recent FFmpeg heartbeat, including jobs
        // started by a worker from before the progress migration.
        \App\Models\ScheduledVideo::where('processing_status', 'processing')
            ->whereRaw('COALESCE(processing_heartbeat_at, processing_started_at) < ?', [now()->subMinutes(65)])
            ->update(['processing_status' => 'failed', 'processing_error' => 'La conversión se interrumpió. Puedes reintentar.']);
        \App\Models\InstitutionalAd::where('processing_status', 'processing')
            ->whereRaw('COALESCE(processing_heartbeat_at, processing_started_at) < ?', [now()->subMinutes(65)])
            ->update(['processing_status' => 'failed', 'processing_error' => 'La conversión se interrumpió. Puedes reintentar.']);
        $ids = \App\Models\ScheduledVideo::where('processing_status', 'pending')->orderBy('id')->limit(10)->pluck('id');
        foreach ($ids as $id) app(\App\Services\ScheduledVideoProcessor::class)->process($id);
        $adIds = \App\Models\InstitutionalAd::where('processing_status', 'pending')->orderBy('id')->limit(10)->pluck('id');
        foreach ($adIds as $id) app(\App\Services\InstitutionalAdProcessor::class)->process($id);
        if (!$this->option('watch')) break;
        sleep(3);
    } while (true);
})->purpose('Convertir programación y publicidad institucional a MP4 optimizado');
