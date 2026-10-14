<?php

return [
    'rtmp_url' => env('STREAMING_RTMP_URL'),
    'hls_base_url' => rtrim(env('STREAMING_HLS_BASE_URL', ''), '/'),
    'recordings_root' => env('STREAMING_RECORDINGS_ROOT', '/var/lib/uebtv/recordings'),
    'x_accel_media' => (bool) env('STREAMING_X_ACCEL_MEDIA', false),
    'reconnect_grace' => (int) env('STREAMING_RECONNECT_GRACE', 90),
    'ffmpeg' => env('STREAMING_FFMPEG', '/usr/bin/ffmpeg'),
    'ffprobe' => env('STREAMING_FFPROBE', '/usr/bin/ffprobe'),
    'control_url' => env('STREAMING_CONTROL_URL', 'http://127.0.0.1:8091/control'),
    'callback_token' => env('STREAMING_CALLBACK_TOKEN'),
];
