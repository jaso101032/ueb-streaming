<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Límites de subida de videos. No hay un tope fijo de tamaño: lo que manda es
 * el espacio libre real del servidor. Un video necesita sitio para el original
 * y para la versión optimizada (y luego sus calidades), así que se reserva el
 * doble de su tamaño más un margen de seguridad.
 */
class UploadLimits
{
    /** Margen que siempre se deja libre para grabaciones en vivo y el sistema. */
    public const RESERVE_BYTES = 2 * 1024 ** 3;

    /** Solo para pruebas: simula el espacio libre del disco. */
    public static ?int $fakeFreeBytes = null;

    public static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (float) $value;
        $factor = match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };
        return max(0, (int) ($number * $factor));
    }

    /** Bytes libres donde se guardan los videos (y en la carpeta temporal de subidas). */
    public static function freeBytes(): ?int
    {
        if (self::$fakeFreeBytes !== null) return self::$fakeFreeBytes;
        // Respeta el cupo de medios (STREAMING_MEDIA_QUOTA_GB) si está configurado.
        $usage = StorageUsage::recordings();
        $free = $usage['available'] ? (int) $usage['freeBytes'] : null;
        $paths = array_filter([Storage::disk('local')->path(''), ini_get('upload_tmp_dir') ?: sys_get_temp_dir()]);
        foreach ($paths as $path) {
            $value = @disk_free_space($path);
            if (is_numeric($value)) $free = $free === null ? (int) $value : min($free, (int) $value);
        }

        return $free;
    }

    /** Tamaño máximo de video que cabe ahora mismo (original + optimizado + margen). */
    public static function spaceForVideo(): ?int
    {
        $free = self::freeBytes();

        return $free === null ? null : max(0, intdiv($free - self::RESERVE_BYTES, 2));
    }

    public static function scheduled(): array
    {
        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        // Límite técnico de PHP (muy alto en producción); deja sitio para el resto del formulario.
        $server = min($upload ?: PHP_INT_MAX, $post ? max(0, $post - 1024 * 1024) : PHP_INT_MAX);
        $space = self::spaceForVideo();
        $maximum = min($server, $space ?? PHP_INT_MAX);

        return [
            'maxBytes' => $maximum, 'maxMb' => round($maximum / 1048576, 1),
            'freeBytes' => self::freeBytes(),
            // Por qué hay límite: 'space' (poco espacio libre) o 'server' (configuración de PHP).
            'reason' => $space !== null && $space <= $server ? 'space' : 'server',
            'noSpace' => $space !== null && $space < 50 * 1024 ** 2,
            'serverLimited' => $server < 1024 ** 3,
            'freeUrl' => route('admin.media.index', ['liberar' => 1]),
        ];
    }

    /** Rechaza la subida si el video no cabe en el espacio libre. */
    public static function ensureRoomFor(UploadedFile $file, string $field): void
    {
        $space = self::spaceForVideo();
        if ($space !== null && $file->getSize() > $space) {
            throw ValidationException::withMessages([$field => sprintf(
                'No hay espacio suficiente en el servidor para este video (%s). Ahora caben hasta %s. Libera espacio en Biblioteca de medios → Liberar espacio.',
                self::human($file->getSize()), self::human($space),
            )]);
        }
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1024 ** 3 ? number_format($bytes / 1024 ** 3, 1, ',', '.').' GB' : number_format($bytes / 1024 ** 2, 0, ',', '.').' MB';
    }
}
