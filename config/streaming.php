<?php

return [
    'rtmp_url' => env('STREAMING_RTMP_URL'),
    'hls_base_url' => rtrim(env('STREAMING_HLS_BASE_URL', ''), '/'),
    'hls_root' => env('STREAMING_HLS_ROOT', '/tmp/hls'),
    'recordings_root' => env('STREAMING_RECORDINGS_ROOT', '/var/lib/uebtv/recordings'),
    'media_quota_gb' => env('STREAMING_MEDIA_QUOTA_GB'),
    'media_storage_root' => storage_path('app'),
    'x_accel_media' => (bool) env('STREAMING_X_ACCEL_MEDIA', false),
    'reconnect_grace' => (int) env('STREAMING_RECONNECT_GRACE', 90),
    'ffmpeg' => env('STREAMING_FFMPEG', '/usr/bin/ffmpeg'),
    'ffprobe' => env('STREAMING_FFPROBE', '/usr/bin/ffprobe'),
    'control_url' => env('STREAMING_CONTROL_URL', 'http://127.0.0.1:8091/control'),
    'callback_token' => env('STREAMING_CALLBACK_TOKEN'),
    // Política de conservación: los originales (FLV de OBS y videos subidos) se
    // conservan tras la conversión y se registra su huella SHA-256. Solo se
    // eliminan si la institución lo decide expresamente con false.
    'keep_sources' => filter_var(env('STREAMING_KEEP_SOURCES', true), FILTER_VALIDATE_BOOL),
];
