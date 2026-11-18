<?php

namespace App\Console\Commands;

use App\Services\AutomaticSubtitles;
use Illuminate\Console\Command;

class GenerateSubtitles extends Command
{
    protected $signature = 'subtitles:generate {--watch : Mantener el trabajador activo} {--once : Procesar como máximo un video}';

    protected $description = 'Genera subtítulos automáticos (Whisper) para los videos almacenados, de uno en uno.';

    public function handle(AutomaticSubtitles $subtitles): int
    {
        if (! config('streaming.auto_subtitles.enabled')) {
            $this->info('Los subtítulos automáticos están desactivados (STREAMING_AUTO_SUBTITLES=false).');
            if (! $this->option('watch')) return self::SUCCESS;
            // Mantener el contenedor en pie sin trabajar ni reiniciarse en bucle.
            while (true) sleep(3600);
        }
        // Un solo trabajador a la vez: transcribir usa mucha CPU.
        // Un candado por entorno: las pruebas no chocan con un trabajador local.
        $lock = fopen(storage_path('framework/subtitles-worker-'.app()->environment().'.lock'), 'c');
        if (! $lock) return self::FAILURE;
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $this->info('Ya hay otro trabajador de subtítulos activo.');

            return self::SUCCESS;
        }
        try {
            $subtitles->recoverInterrupted();
            do {
                $media = $subtitles->next();
                if ($media) {
                    $this->line('Transcribiendo '.class_basename($media).' #'.$media->getKey().'…');
                    $this->line('Resultado: '.$subtitles->process($media));
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
