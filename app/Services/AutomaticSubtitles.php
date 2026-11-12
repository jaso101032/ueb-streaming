<?php

namespace App\Services;

use App\Models\InstitutionalAd;
use App\Models\ScheduledVideo;
use App\Models\Transmision;
use App\Support\Audit;
use App\Support\Subtitles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Subtítulos automáticos de videos almacenados: grabaciones de directos ya
 * terminados, videos programados y publicidad. El directo no se transcribe.
 *
 * Un único trabajador procesa los videos de uno en uno. Nunca reemplaza
 * subtítulos subidos por el personal ni vuelve a generar los que se quitaron.
 */
class AutomaticSubtitles
{
    public const MODELS = [Transmision::class, ScheduledVideo::class, InstitutionalAd::class];

    private const EXIT_NO_SPEECH = 3;

    private const EXIT_MISSING_ENGINE = 4;

    /** Videos listos que todavía necesitan subtítulos automáticos. */
    public function candidates(string $model): Builder
    {
        $query = $model::query()->whereNull('subtitles_path')
            ->where(fn ($q) => $q->whereNull('subtitles_status')->orWhere('subtitles_status', 'pending')
                ->orWhere(fn ($q) => $q->where('subtitles_status', 'failed')
                    ->where('subtitles_attempts', '<', config('streaming.auto_subtitles.max_attempts'))
                    ->where('subtitles_attempt_at', '<', now()->subMinutes(10))));

        return $model === Transmision::class
            ? $query->where('recording_status', 'ready')->whereNotNull('recording_file')
            : $query->where('processing_status', 'ready')->whereNotNull('path');
    }

    /** Siguiente video por transcribir, o null si no hay trabajo. */
    public function next(): ?Model
    {
        foreach (self::MODELS as $model) {
            foreach ($this->candidates($model)->orderBy('id')->limit(20)->get() as $media) {
                if ($this->mediaPath($media)) return $media;
                // Un video sin archivo no se puede transcribir; no insistir.
                $media->update(['subtitles_status' => 'failed', 'subtitles_progress' => null,
                    'subtitles_attempts' => config('streaming.auto_subtitles.max_attempts'), 'subtitles_attempt_at' => now(),
                    'subtitles_error' => 'No se encontró el archivo de video.']);
            }
        }

        return null;
    }

    /** Los trabajos "processing" de un trabajador que se detuvo vuelven a la cola. */
    public function recoverInterrupted(): void
    {
        foreach (self::MODELS as $model) {
            $model::where('subtitles_status', 'processing')->update(['subtitles_status' => 'pending', 'subtitles_progress' => null]);
        }
    }

    public function mediaPath(Model $media): ?string
    {
        if ($media instanceof Transmision) return $media->recordingPath();
        if ($media->processing_status !== 'ready' || ! $media->path) return null;
        $disk = Storage::disk('local');

        return $disk->exists($media->path) ? $disk->path($media->path) : null;
    }

    public function process(Model $media): string
    {
        $media = $this->claim($media);
        if (! $media) return 'skipped';
        $input = $this->mediaPath($media);
        $output = tempnam(sys_get_temp_dir(), 'uebtv-subtitles-');
        $settings = config('streaming.auto_subtitles');
        try {
            if (! $input) return $this->fail($media, 'No se encontró el archivo de video.', true);
            $command = [$settings['python'], $settings['script'], $input, $output,
                '--model', (string) $settings['model'], '--threads', (string) max(1, $settings['threads']),
                '--language', (string) $settings['language'], '--ffmpeg', (string) config('streaming.ffmpeg')];
            if ($settings['model_dir']) array_push($command, '--model-dir', $settings['model_dir']);
            // Prioridad baja: la web y las conversiones de video van primero.
            if (is_executable('/usr/bin/nice')) array_unshift($command, '/usr/bin/nice', '-n', '10');

            $process = new Process($command, base_path());
            $process->setTimeout($settings['timeout'] ?: null);
            $last = ['value' => 0, 'at' => 0.0];
            $buffer = '';
            $process->run(function ($type, $chunk) use ($media, &$last, &$buffer) {
                if ($type !== Process::OUT) return;
                $buffer .= $chunk;
                while (($line = strstr($buffer, "\n", true)) !== false) {
                    $buffer = substr($buffer, strlen($line) + 1);
                    if (! preg_match('/^PROGRESS (\d{1,3})$/', trim($line), $match)) continue;
                    $value = min(99, (int) $match[1]);
                    // Guardar el avance sin llenar la base de datos de escrituras.
                    if ($value >= $last['value'] + 5 || microtime(true) - $last['at'] > 30) {
                        $media->update(['subtitles_progress' => $value]);
                        $last = ['value' => $value, 'at' => microtime(true)];
                    }
                }
            });

            if ($process->getExitCode() === self::EXIT_NO_SPEECH) {
                return $this->finish($media, ['subtitles_status' => 'no_speech', 'subtitles_progress' => null,
                    'subtitles_error' => null], 'no_speech');
            }
            if ($process->getExitCode() === self::EXIT_MISSING_ENGINE) {
                return $this->fail($media, 'El motor de transcripción (faster-whisper) no está instalado en el servidor.');
            }
            if (! $process->isSuccessful()) {
                report(new \RuntimeException('Subtitle generation failed: '.mb_substr($process->getErrorOutput(), -500)));

                return $this->fail($media, 'No se pudieron generar los subtítulos. Se reintentará automáticamente.');
            }
            $path = Subtitles::storeText((string) file_get_contents($output));
            if (! $path) return $this->fail($media, 'La transcripción no produjo subtítulos válidos.');

            $saved = $this->finish($media, ['subtitles_path' => $path, 'subtitles_source' => 'auto', 'subtitles_status' => 'ready',
                'subtitles_progress' => 100, 'subtitles_error' => null], 'ready');
            if ($saved !== 'ready') Subtitles::delete($path);

            return $saved;
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($media, 'No se pudieron generar los subtítulos. Se reintentará automáticamente.');
        } finally {
            if (is_file($output)) @unlink($output);
        }
    }

    /** Marca el video como en proceso, salvo que otro cambio se haya adelantado. */
    private function claim(Model $media): ?Model
    {
        return DB::transaction(function () use ($media) {
            $fresh = $media::whereKey($media->getKey())->lockForUpdate()->first();
            if (! $fresh || $fresh->subtitles_path || ! in_array($fresh->subtitles_status, [null, 'pending', 'failed'], true)) return null;
            $fresh->update(['subtitles_status' => 'processing', 'subtitles_progress' => 0, 'subtitles_error' => null,
                'subtitles_attempts' => $fresh->subtitles_attempts + 1, 'subtitles_attempt_at' => now()]);

            return $fresh;
        });
    }

    /**
     * Guarda el resultado solo si nadie subió ni quitó subtítulos mientras se
     * transcribía: lo que haga el personal siempre tiene prioridad.
     */
    private function finish(Model $media, array $values, string $result): string
    {
        return DB::transaction(function () use ($media, $values, $result) {
            $fresh = $media::whereKey($media->getKey())->lockForUpdate()->first();
            if (! $fresh || $fresh->subtitles_path || $fresh->subtitles_status !== 'processing') return 'superseded';
            $fresh->update($values);
            if ($result === 'ready') {
                Audit::record('subtitulos.generados_automaticamente', $fresh instanceof InstitutionalAd ? $fresh : $fresh->evento, [], 'Sistema');
            }

            return $result;
        });
    }

    private function fail(Model $media, string $message, bool $final = false): string
    {
        $values = ['subtitles_status' => 'failed', 'subtitles_progress' => null, 'subtitles_error' => $message];
        if ($final) $values['subtitles_attempts'] = config('streaming.auto_subtitles.max_attempts');

        return $this->finish($media, $values, 'failed') === 'failed' ? 'failed' : 'superseded';
    }
}
