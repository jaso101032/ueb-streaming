<?php

namespace App\Services;

use App\Models\ScheduledVideo;
use App\Models\Transmision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Calidades adaptativas (HLS) de grabaciones y videos programados.
 *
 * A partir del MP4 optimizado genera, en una sola pasada de FFmpeg, las
 * calidades de la escalera configurada que no superan la altura del video
 * (1080p, 720p, 480p y 360p). Los cuadros clave quedan alineados cada dos
 * segundos para que el reproductor pueda cambiar de calidad sin cortes.
 * El MP4 sigue disponible como respaldo y para descargas.
 */
class AdaptiveRenditions
{
    public const MODELS = [Transmision::class, ScheduledVideo::class];

    public const ROOT = 'renditions';

    /** Archivos que el reproductor puede pedir dentro de la carpeta de calidades. */
    public const FILE_PATTERN = '(?:master\.m3u8|\d{3,4}p/(?:index\.m3u8|seg_\d{5}\.ts))';

    public function candidates(string $model): Builder
    {
        $query = $model::query()
            ->where(fn ($q) => $q->whereNull('renditions_status')->orWhere('renditions_status', 'pending')
                ->orWhere(fn ($q) => $q->where('renditions_status', 'failed')
                    ->where('renditions_attempts', '<', config('streaming.renditions.max_attempts'))
                    ->where('renditions_attempt_at', '<', now()->subMinutes(10))));

        return $model === Transmision::class
            ? $query->where('recording_status', 'ready')->whereNotNull('recording_file')
            : $query->where('processing_status', 'ready')->whereNotNull('path');
    }

    public function next(): ?Model
    {
        foreach (self::MODELS as $model) {
            foreach ($this->candidates($model)->orderBy('id')->limit(20)->get() as $media) {
                if ($this->mediaPath($media)) return $media;
                $media->update(['renditions_status' => 'failed', 'renditions_progress' => null,
                    'renditions_attempts' => config('streaming.renditions.max_attempts'), 'renditions_attempt_at' => now(),
                    'renditions_error' => 'No se encontró el archivo de video.']);
            }
        }

        return null;
    }

    public function recoverInterrupted(): void
    {
        foreach (self::MODELS as $model) {
            $model::where('renditions_status', 'processing')->update(['renditions_status' => 'pending', 'renditions_progress' => null]);
        }
    }

    public function mediaPath(Model $media): ?string
    {
        if ($media instanceof Transmision) return $media->recordingPath();
        if ($media->processing_status !== 'ready' || ! $media->path) return null;
        $disk = Storage::disk('local');

        return $disk->exists($media->path) ? $disk->path($media->path) : null;
    }

    /** Ruta absoluta de un archivo de calidades listo, o null. */
    public static function file(?Model $media, string $file): ?string
    {
        if (! $media || $media->renditions_status !== 'ready' || ! self::validDirectory($media->renditions_path)
            || ! preg_match('~^'.self::FILE_PATTERN.'$~D', $file)) return null;
        $relative = $media->renditions_path.'/'.$file;
        $disk = Storage::disk('local');

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    public static function validDirectory(?string $path): bool
    {
        return (bool) preg_match('~^'.self::ROOT.'/(?:recording|scheduled)-\d+-[A-Za-z0-9]{12}$~D', $path ?? '');
    }

    public static function deleteFiles(?Model $media): void
    {
        if ($media && self::validDirectory($media->renditions_path)) {
            Storage::disk('local')->deleteDirectory($media->renditions_path);
        }
    }

    /** Calidades a generar para un video de esta altura. */
    public function ladderFor(int $height): array
    {
        $ladder = array_values(array_filter(config('streaming.renditions.ladder'), fn ($rung) => $rung['height'] <= $height));
        if ($ladder) return $ladder;
        // Video más pequeño que la menor calidad: una sola, en su tamaño.
        $smallest = collect(config('streaming.renditions.ladder'))->sortBy('height')->first();
        $even = max(2, $height - $height % 2);

        return [['name' => $even.'p', 'height' => $even] + $smallest];
    }

    public function process(Model $media): string
    {
        $media = $this->claim($media);
        if (! $media) return 'skipped';
        $settings = config('streaming.renditions');
        $directory = self::ROOT.'/'.($media instanceof Transmision ? 'recording' : 'scheduled').'-'.$media->getKey().'-'.Str::random(12);
        $disk = Storage::disk('local');
        try {
            $input = $this->mediaPath($media);
            if (! $input) return $this->fail($media, 'No se encontró el archivo de video.', true);
            // Sin espacio suficiente no se intenta: el MP4 sigue funcionando.
            $free = @disk_free_space($disk->path(''));
            if (is_numeric($free) && $free < filesize($input) * 2.5 + 512 * 1024 ** 2) {
                return $this->fail($media, 'No hay espacio suficiente en el disco para preparar las calidades.');
            }
            $info = $this->probe($input);
            $ladder = $this->ladderFor($info['height']);
            $disk->makeDirectory($directory);
            $output = $disk->path($directory);

            $process = new Process($this->command($input, $output, $ladder, $info['audio'], $settings), base_path());
            $process->setTimeout(max(3600, min(172800, (int) ($info['duration'] * 8))));
            $last = ['value' => 0, 'at' => 0.0];
            $buffer = '';
            $process->run(function ($type, $chunk) use ($media, $info, &$last, &$buffer) {
                if ($type !== Process::OUT) return;
                $buffer .= $chunk;
                while (($line = strstr($buffer, "\n", true)) !== false) {
                    $buffer = substr($buffer, strlen($line) + 1);
                    if (! preg_match('/^out_time_us=(\d+)$/', trim($line), $match) || $info['duration'] <= 0) continue;
                    $value = min(99, (int) floor($match[1] / 1_000_000 / $info['duration'] * 100));
                    if ($value >= $last['value'] + 5 || microtime(true) - $last['at'] > 30) {
                        $media->update(['renditions_progress' => $value]);
                        $last = ['value' => $value, 'at' => microtime(true)];
                    }
                }
            });
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('FFmpeg could not create renditions: '.mb_substr($process->getErrorOutput(), -500));
            }
            $levels = $this->verify($output, $ladder);

            $saved = $this->finish($media, ['renditions_path' => $directory, 'renditions_status' => 'ready', 'renditions_progress' => 100,
                'renditions_error' => null, 'renditions_levels' => $levels], 'ready', $previous);
            if ($saved === 'ready') {
                if ($previous && $previous !== $directory && self::validDirectory($previous)) $disk->deleteDirectory($previous);
            } else {
                $disk->deleteDirectory($directory);
            }

            return $saved;
        } catch (Throwable $exception) {
            report($exception);
            $disk->deleteDirectory($directory);

            return $this->fail($media, 'No se pudieron preparar las calidades del video. Se reintentará automáticamente.');
        }
    }

    /** @return array{width:int,height:int,duration:float,audio:bool} */
    private function probe(string $path): array
    {
        $process = new Process([config('streaming.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file',
            '-show_entries', 'format=duration:stream=codec_type,width,height', '-of', 'json', $path]);
        $process->setTimeout(60)->mustRun();
        $info = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $streams = collect($info['streams'] ?? []);
        $video = $streams->firstWhere('codec_type', 'video');
        if (! $video || ($video['height'] ?? 0) < 2) throw new \RuntimeException('The file has no video stream.');

        return ['width' => (int) $video['width'], 'height' => (int) $video['height'],
            'duration' => (float) ($info['format']['duration'] ?? 0), 'audio' => $streams->contains('codec_type', 'audio')];
    }

    private function command(string $input, string $output, array $ladder, bool $audio, array $settings): array
    {
        $count = count($ladder);
        $filters = $count > 1 ? ['[0:v:0]split='.$count.implode('', array_map(fn ($i) => "[s$i]", array_keys($ladder)))] : [];
        foreach ($ladder as $i => $rung) {
            $from = $count > 1 ? "[s$i]" : '[0:v:0]';
            $filters[] = $from.'scale=w=-2:h='.$rung['height'].':flags=bicubic,setsar=1'."[v$i]";
        }
        $command = [config('streaming.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-nostats',
            '-progress', 'pipe:1', '-y', '-protocol_whitelist', 'file', '-i', $input,
            '-filter_complex', implode(';', $filters)];
        $streams = [];
        foreach ($ladder as $i => $rung) {
            array_push($command, '-map', "[v$i]");
            if ($audio) array_push($command, '-map', '0:a:0');
            $kbps = $rung['video_kbps'];
            // CRF con tope: los videos estáticos (diapositivas) pesan menos y
            // los de mucho movimiento no superan el ancho de banda declarado.
            array_push($command, "-maxrate:v:$i", $kbps.'k', "-bufsize:v:$i", ($kbps * 2).'k',
                "-profile:v:$i", $rung['height'] >= 720 ? 'high' : 'main');
            if ($audio) array_push($command, "-b:a:$i", $rung['audio_kbps'].'k');
            $streams[] = "v:$i".($audio ? ",a:$i" : '').',name:'.$rung['name'];
        }
        array_push($command, '-c:v', 'libx264', '-preset', (string) $settings['preset'], '-crf', '23', '-pix_fmt', 'yuv420p',
            '-force_key_frames', 'expr:gte(t,n_forced*2)', '-sc_threshold', '0');
        if ($audio) array_push($command, '-c:a', 'aac', '-ac', '2');
        if ($settings['threads'] > 0) array_push($command, '-threads', (string) $settings['threads']);
        array_push($command, '-f', 'hls', '-hls_time', (string) $settings['segment_seconds'], '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments', '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', $output.'/%v/seg_%05d.ts', '-master_pl_name', 'master.m3u8',
            '-var_stream_map', implode(' ', $streams), $output.'/%v/index.m3u8');
        // Prioridad baja: la web y el directo van primero.
        if (is_executable('/usr/bin/nice')) array_unshift($command, '/usr/bin/nice', '-n', '10');

        return $command;
    }

    /** Comprueba que cada calidad quedó completa; devuelve sus dimensiones. */
    private function verify(string $output, array $ladder): array
    {
        $master = @file_get_contents($output.'/master.m3u8');
        if (! $master || ! str_contains($master, '#EXT-X-STREAM-INF')) throw new \RuntimeException('Missing master playlist.');
        $levels = [];
        foreach ($ladder as $rung) {
            $playlist = @file_get_contents($output.'/'.$rung['name'].'/index.m3u8');
            if (! $playlist || ! str_contains($playlist, '#EXT-X-ENDLIST') || ! str_contains($master, $rung['name'].'/index.m3u8')) {
                throw new \RuntimeException('Incomplete rendition '.$rung['name']);
            }
            preg_match('/RESOLUTION=(\d+)x(\d+)[^\n]*\n'.preg_quote($rung['name'], '/').'\/index\.m3u8/', $master, $match);
            $levels[] = ['name' => $rung['name'], 'width' => (int) ($match[1] ?? 0), 'height' => (int) ($match[2] ?? $rung['height'])];
        }

        return $levels;
    }

    private function claim(Model $media): ?Model
    {
        return DB::transaction(function () use ($media) {
            $fresh = $media::whereKey($media->getKey())->lockForUpdate()->first();
            if (! $fresh || ! in_array($fresh->renditions_status, [null, 'pending', 'failed'], true)) return null;
            $fresh->update(['renditions_status' => 'processing', 'renditions_progress' => 0, 'renditions_error' => null,
                'renditions_attempts' => $fresh->renditions_attempts + 1, 'renditions_attempt_at' => now()]);

            return $fresh;
        });
    }

    private function finish(Model $media, array $values, string $result, ?string &$previous = null): string
    {
        return DB::transaction(function () use ($media, $values, $result, &$previous) {
            $fresh = $media::whereKey($media->getKey())->lockForUpdate()->first();
            if (! $fresh || $fresh->renditions_status !== 'processing') return 'superseded';
            $previous = $fresh->renditions_path;
            $fresh->update($values);

            return $result;
        });
    }

    private function fail(Model $media, string $message, bool $final = false): string
    {
        $values = ['renditions_status' => 'failed', 'renditions_progress' => null, 'renditions_error' => $message];
        if ($final) $values['renditions_attempts'] = config('streaming.renditions.max_attempts');

        return $this->finish($media, $values, 'failed') === 'failed' ? 'failed' : 'superseded';
    }
}
