<?php

namespace App\Support;

class UploadLimits
{
    public static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (float) $value;
        $factor = match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };
        return max(0, (int) ($number * $factor));
    }

    public static function scheduled(): array
    {
        $cap = 500 * 1024 * 1024;
        $upload = self::bytes(ini_get('upload_max_filesize'));
        $post = self::bytes(ini_get('post_max_size'));
        // Leave room for multipart headers and the other form fields.
        $maximum = min($cap, $upload ?: $cap, $post ? max(0, $post - 1024 * 1024) : $cap);
        return ['maxBytes' => $maximum, 'maxMb' => round($maximum / 1048576, 1),
            'serverLimited' => $maximum < $cap];
    }
}
