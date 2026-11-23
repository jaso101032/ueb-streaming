<?php

namespace App\Support;

/**
 * Espacio de almacenamiento de UEBTV: cuánto hay libre, en qué se gasta y si
 * hay que avisar al administrador. Lo usan la barra lateral, el Resumen, el
 * aviso del panel y los límites de subida, así todos muestran la misma cifra.
 */
class StorageUsage
{
    /** Por debajo de este porcentaje libre se avisa (amarillo). */
    public const LOW_PERCENT = 15;

    /** Categorías del desglose, en el orden en que se muestran. */
    public const CATEGORIES = [
        'recordings' => 'Grabaciones en vivo',
        'scheduled' => 'Videos de la parrilla',
        'ads' => 'Publicidad',
        'renditions' => 'Calidades (720p, 480p…)',
        'other' => 'Miniaturas, subtítulos y otros',
    ];

    private static ?array $cache = null;

    private static ?int $cacheFor = null;

    /** Olvida el cálculo guardado (tras borrar o subir archivos, y en pruebas). */
    public static function flush(): void
    {
        self::$cache = null;
        self::$cacheFor = null;
    }

    public static function recordings(): array
    {
        // Se mide una vez por petición: la barra lateral, el aviso y el Resumen
        // comparten el resultado sin recorrer las carpetas varias veces.
        $request = spl_object_id(request());
        if (self::$cache === null || self::$cacheFor !== $request) {
            self::$cache = self::measure();
            self::$cacheFor = $request;
        }

        return self::$cache;
    }

    private static function measure(): array
    {
        $path = config('streaming.recordings_root');
        $mediaPath = config('streaming.media_storage_root');

        if (! is_string($path) || $path === '' || ! is_dir($path) ||
            ! is_string($mediaPath) || ! is_dir($mediaPath)) {
            return ['available' => false, 'level' => 'ok'];
        }

        $diskTotal = @disk_total_space($path);
        $recordingFree = @disk_free_space($path);
        $mediaFree = @disk_free_space($mediaPath);

        if (! is_numeric($diskTotal) || ! is_numeric($recordingFree) || ! is_numeric($mediaFree) ||
            $diskTotal <= 0 || $recordingFree < 0 || $mediaFree < 0) {
            return ['available' => false, 'level' => 'ok'];
        }

        // Include originals, MP4s, scheduled videos, ads and uploads. Never
        // follow symlinks: a public storage link must not count external files.
        // If the recordings live inside the media folder (local setup), they
        // are counted only once.
        $breakdown = array_fill_keys(array_keys(self::CATEGORIES), 0);
        $recordingUsed = self::directoryBytes($path);
        $uploads = self::mediaBytes($mediaPath, $path);
        if ($recordingUsed === null || $uploads === null) {
            return ['available' => false, 'level' => 'ok'];
        }
        $breakdown['recordings'] = $recordingUsed;
        foreach ($uploads as $key => $bytes) $breakdown[$key] += $bytes;
        $mediaUsed = array_sum($breakdown);
        $physicalFree = min((int) $recordingFree, (int) $mediaFree);
        $quotaGb = config('streaming.media_quota_gb');
        $quota = is_numeric($quotaGb) && (float) $quotaGb > 0
            ? (int) round((float) $quotaGb * 1024 ** 3) : null;

        if ($quota !== null) {
            $total = $quota;
            $used = min($mediaUsed, $total);
            $remaining = max(0, $total - $mediaUsed);
            $basis = 'quota';
        } else {
            // Disk size is not necessarily a provider-assigned application quota.
            $total = (int) $diskTotal;
            $remaining = min((int) $recordingFree, $total);
            $used = max(0, $total - $remaining);
            $basis = 'filesystem';
        }
        $available = min($remaining, $physicalFree);
        $level = self::level($available, $total);

        return [
            'available' => true,
            'totalBytes' => $total,
            'freeBytes' => $available,
            'usedBytes' => $used,
            'usedPercent' => (int) round(($used / $total) * 100),
            'mediaUsedBytes' => $mediaUsed,
            'basis' => $basis,
            'physicalLimited' => $physicalFree < $remaining,
            'lowSpace' => $level !== 'ok',
            // ok · low (avisar) · critical (ya no caben videos ni grabaciones largas)
            'level' => $level,
            'breakdown' => collect(self::CATEGORIES)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'bytes' => $breakdown[$key],
            ])->values()->all(),
            'freeUrl' => route('admin.media.index', ['liberar' => 1]),
        ];
    }

    /**
     * Crítico: no queda ni el margen que se reserva para grabar en vivo.
     * Bajo: menos del 15 % libre.
     */
    public static function level(int $free, int $total): string
    {
        if ($free <= UploadLimits::RESERVE_BYTES) return 'critical';

        return $free <= $total * self::LOW_PERCENT / 100 ? 'low' : 'ok';
    }

    /** Bytes por categoría dentro de la carpeta de medios (sin las grabaciones). */
    private static function mediaBytes(string $root, string $recordings): ?array
    {
        $root = rtrim(realpath($root) ?: $root, '/');
        $skip = rtrim(realpath($recordings) ?: $recordings, '/').'/';
        $bytes = array_fill_keys(array_keys(self::CATEGORIES), 0);
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if (! $file->isFile() || $file->isLink()) continue;
                $path = $file->getPathname();
                if (str_starts_with($path, $skip)) continue;
                $bytes[self::categoryOf(substr($path, strlen($root) + 1))] += $file->getSize();
            }
        } catch (\Throwable) {
            return null;
        }

        return $bytes;
    }

    private static function categoryOf(string $relative): string
    {
        return match (true) {
            (bool) preg_match('#(^|/)renditions/#', $relative) => 'renditions',
            (bool) preg_match('#(^|/)scheduled-(sources|videos)/#', $relative) => 'scheduled',
            (bool) preg_match('#(^|/)institutional-ad(-sources|s)/#', $relative) => 'ads',
            default => 'other',
        };
    }

    private static function directoryBytes(string $root): ?int
    {
        $bytes = 0;
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile() && ! $file->isLink()) {
                    $bytes += $file->getSize();
                }
            }
        } catch (\Throwable) {
            // A removed file or inaccessible mount must not break the panel.
            return null;
        }

        return $bytes;
    }
}
