<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Valida subtítulos WebVTT o SRT, los normaliza a WebVTT y los guarda en almacenamiento privado. */
class Subtitles
{
    public const MAX_KB = 1024;

    public static function store(UploadedFile $file): string
    {
        $path = self::storeText((string) file_get_contents($file->getRealPath()));
        if ($path === null) {
            throw ValidationException::withMessages(['subtitles' => 'El archivo no contiene subtítulos válidos. Usa un archivo .vtt o .srt con tiempos como 00:00:01,000 --> 00:00:04,000.']);
        }

        return $path;
    }

    /** Valida, normaliza y guarda subtítulos en texto; null si no son válidos. */
    public static function storeText(string $text): ?string
    {
        $vtt = self::toWebVtt($text);
        if ($vtt === null) return null;
        $path = 'subtitles/'.Str::uuid().'.vtt';
        Storage::disk('local')->put($path, $vtt);

        return $path;
    }

    /**
     * Estado de los subtítulos de un video para el panel de administración.
     * $urls: uploadUrl, removeUrl, generateUrl y previewUrl.
     */
    public static function adminState($media, array $urls): array
    {
        $present = (bool) $media->subtitles_path;
        $status = $media->subtitles_status;
        $auto = config('streaming.auto_subtitles.enabled');
        // Se puede pedir una nueva generación si no hay subtítulos y el
        // trabajador no está ya ocupándose de este video.
        $canGenerate = $auto && ! $present && ! in_array($status, [null, 'pending', 'processing'], true);

        return ['present' => $present, 'source' => $present ? ($media->subtitles_source ?? 'manual') : null,
            'status' => $present ? 'ready' : ($auto ? ($status ?? 'pending') : null),
            'progress' => $media->subtitles_progress, 'error' => $media->subtitles_error,
            'auto' => (bool) $auto,
            'uploadUrl' => $urls['uploadUrl'], 'removeUrl' => $present ? $urls['removeUrl'] : null,
            'generateUrl' => $canGenerate ? $urls['generateUrl'] : null,
            'previewUrl' => $present ? ($urls['previewUrl'] ?? null) : null];
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'subtitles/')) Storage::disk('local')->delete($path);
    }

    /** Devuelve WebVTT válido, o null si no hay al menos una indicación con tiempos. */
    public static function toWebVtt(string $text): ?string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
        if (! mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $time = '(?:\d{1,2}:)?\d{2}:\d{2}[.,]\d{3}';
        $cue = '/^\s*'.$time.'\s+-->\s+'.$time.'/m';
        if (! preg_match($cue, $text)) return null;
        // Remove markup a viewer's browser could interpret and any NUL/control bytes.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/<(?!\/?(?:b|i|u|c(?:\.[\w.-]+)?|v(?:\s[^>]{0,60})?|lang\s[\w-]{1,20})>)[^>]*>/i', '', $text);
        if (str_starts_with(ltrim($text), 'WEBVTT')) return trim($text)."\n";

        // SRT: drop numeric counters, use dot milliseconds.
        $source = explode("\n", trim($text));
        $lines = [];
        foreach ($source as $i => $line) {
            $next = $source[$i + 1] ?? '';
            if (preg_match('/^\s*\d+\s*$/', $line) && preg_match($cue, $next)) continue;
            $lines[] = preg_match($cue, $line) ? preg_replace('/(\d),(\d{3})/', '$1.$2', $line) : $line;
        }

        return "WEBVTT\n\n".implode("\n", $lines)."\n";
    }

    public static function response(?string $path)
    {
        abort_unless($path && str_starts_with($path, 'subtitles/') && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'text/vtt; charset=utf-8',
            'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public static function rules(): array
    {
        // Browsers report .vtt/.srt as text/plain or octet-stream; content is validated in store().
        return ['subtitles' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:vtt,srt']];
    }
}
