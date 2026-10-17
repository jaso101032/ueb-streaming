<?php

namespace App\Support;

class StorageUsage
{
    public static function recordings(): array
    {
        $path = config('streaming.recordings_root');

        if (! is_string($path) || $path === '' || ! is_dir($path)) {
            return ['available' => false];
        }

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! is_numeric($total) || ! is_numeric($free) || $total <= 0 || $free < 0) {
            return ['available' => false];
        }

        $total = (int) $total;
        $free = min((int) $free, $total);
        $used = max(0, $total - $free);

        return [
            'available' => true,
            'totalBytes' => $total,
            'freeBytes' => $free,
            'usedBytes' => $used,
            'usedPercent' => (int) round(($used / $total) * 100),
        ];
    }
}
