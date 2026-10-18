<?php

namespace App\Support;

class StorageUsage
{
    public static function recordings(): array
    {
        $path = config('streaming.recordings_root');
        $mediaPath = config('streaming.media_storage_root');

        if (! is_string($path) || $path === '' || ! is_dir($path) ||
            ! is_string($mediaPath) || ! is_dir($mediaPath)) {
            return ['available' => false];
        }

        $diskTotal = @disk_total_space($path);
        $recordingFree = @disk_free_space($path);
        $mediaFree = @disk_free_space($mediaPath);

        if (! is_numeric($diskTotal) || ! is_numeric($recordingFree) || ! is_numeric($mediaFree) ||
            $diskTotal <= 0 || $recordingFree < 0 || $mediaFree < 0) {
            return ['available' => false];
        }

        // Include originals, MP4s, scheduled videos, ads and uploads. Never
        // follow symlinks: a public storage link must not count external files.
        $recordingUsed = self::directoryBytes($path);
        $uploadUsed = self::directoryBytes($mediaPath);
        if ($recordingUsed === null || $uploadUsed === null) {
            return ['available' => false];
        }
        $mediaUsed = $recordingUsed + $uploadUsed;
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

        return [
            'available' => true,
            'totalBytes' => $total,
            'freeBytes' => $available,
            'usedBytes' => $used,
            'usedPercent' => (int) round(($used / $total) * 100),
            'mediaUsedBytes' => $mediaUsed,
            'basis' => $basis,
            'physicalLimited' => $physicalFree < $remaining,
            'lowSpace' => $available <= $total * 0.1,
        ];
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
