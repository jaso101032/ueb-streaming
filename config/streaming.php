<?php

return [
    'recordings_root' => env('STREAMING_RECORDINGS_ROOT', '/var/lib/uebtv/recordings'),
    'reconnect_grace' => (int) env('STREAMING_RECONNECT_GRACE', 90),
    'ffmpeg' => env('STREAMING_FFMPEG', '/usr/bin/ffmpeg'),
    'ffprobe' => env('STREAMING_FFPROBE', '/usr/bin/ffprobe'),
    'control_url' => env('STREAMING_CONTROL_URL', 'http://127.0.0.1:8091/control'),
    'callback_token' => env('STREAMING_CALLBACK_TOKEN'),
];
