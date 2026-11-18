<?php

namespace App\Console\Commands;

use App\Services\AdaptiveRenditions;
use Illuminate\Console\Command;

class GenerateRenditions extends Command
{
    protected $signature = 'media:renditions {--watch : Mantener el trabajador activo} {--once : Procesar como máximo un video}';

    protected $description = 'Genera las calidades adaptativas (1080p, 720p, 480p y 360p) de grabaciones y videos programados.';

    public function handle(AdaptiveRenditions $renditions): int
    {
        if (! config('streaming.renditions.enabled')) {
            $this->info('Las calidades adaptativas están desactivadas (STREAMING_ADAPTIVE_QUALITY=false).');
            if (! $this->option('watch')) return self::SUCCESS;
            while (true) sleep(3600);
        }
        // Un solo trabajador a la vez: codificar varias calidades usa mucha CPU.
        // Un candado por entorno: las pruebas no chocan con un trabajador local.
        $lock = fopen(storage_path('framework/renditions-worker-'.app()->environment().'.lock'), 'c');
        if (! $lock) return self::FAILURE;
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $this->info('Ya hay otro trabajador de calidades activo.');

            return self::SUCCESS;
        }
        try {
            $renditions->recoverInterrupted();
            do {
                $media = $renditions->next();
                if ($media) {
                    $this->line('Preparando calidades de '.class_basename($media).' #'.$media->getKey().'…');
                    $this->line('Resultado: '.$renditions->process($media));
                    if ($this->option('once')) break;
                    continue;
                }
                if (! $this->option('watch')) break;
                sleep(10);
            } while (true);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return self::SUCCESS;
    }
}
