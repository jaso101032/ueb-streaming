<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scheduled:process {--watch : Mantener el procesador activo}', function () {
    do {
        // A killed worker is recoverable, but never overlap its one-hour conversion lease.
        \App\Models\ScheduledVideo::where('processing_status', 'processing')->where('processing_started_at', '<', now()->subMinutes(65))
            ->update(['processing_status' => 'failed', 'processing_error' => 'La conversión se interrumpió. Puedes reintentar.']);
        $ids = \App\Models\ScheduledVideo::where('processing_status', 'pending')->orderBy('id')->limit(10)->pluck('id');
        foreach ($ids as $id) app(\App\Services\ScheduledVideoProcessor::class)->process($id);
        if (!$this->option('watch')) break;
        sleep(3);
    } while (true);
})->purpose('Convertir los videos programados a MP4 optimizado');
