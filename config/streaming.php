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
    // Altura máxima del MP4 optimizado (videos subidos y grabaciones). Un video
    // 2K o 4K se reduce sin deformarse; uno más pequeño no se agranda.
    'max_height' => (int) env('STREAMING_MAX_HEIGHT', 1080),
    // Calidades adaptativas (HLS) para grabaciones y videos programados: el
    // reproductor elige la mejor según la conexión y el público puede cambiarla.
    // Las genera `php artisan media:renditions --watch`, un video a la vez.
    'renditions' => [
        'enabled' => filter_var(env('STREAMING_ADAPTIVE_QUALITY', true), FILTER_VALIDATE_BOOL),
        // Solo se generan las calidades que no superan la altura del video.
        'ladder' => [
            ['name' => '1080p', 'height' => 1080, 'video_kbps' => 5000, 'audio_kbps' => 128],
            ['name' => '720p', 'height' => 720, 'video_kbps' => 2800, 'audio_kbps' => 128],
            ['name' => '480p', 'height' => 480, 'video_kbps' => 1400, 'audio_kbps' => 96],
            ['name' => '360p', 'height' => 360, 'video_kbps' => 800, 'audio_kbps' => 96],
        ],
        'preset' => env('STREAMING_RENDITIONS_PRESET', 'fast'),
        'threads' => (int) env('STREAMING_RENDITIONS_THREADS', 0), // 0 = todos los núcleos
        'segment_seconds' => 4,
        'max_attempts' => 3,
    ],
    // Subtítulos automáticos para videos almacenados (no para el directo).
    // Los genera el trabajador `php artisan subtitles:generate --watch`, de uno
    // en uno, con faster-whisper en la CPU del servidor. Sin costo por minuto.
    'auto_subtitles' => [
        'enabled' => filter_var(env('STREAMING_AUTO_SUBTITLES', true), FILTER_VALIDATE_BOOL),
        'python' => env('STREAMING_SUBTITLES_PYTHON', '/usr/bin/python3'),
        'script' => env('STREAMING_SUBTITLES_SCRIPT', base_path('scripts/generate-subtitles.py')),
        // tiny, base, small (recomendado), medium… Más grande = mejor y más lento.
        'model' => env('STREAMING_SUBTITLES_MODEL', 'small'),
        'model_dir' => env('STREAMING_SUBTITLES_MODEL_DIR'),
        'threads' => (int) env('STREAMING_SUBTITLES_THREADS', 2),
        'language' => env('STREAMING_SUBTITLES_LANGUAGE', 'es'),
        'timeout' => (int) env('STREAMING_SUBTITLES_TIMEOUT', 6 * 3600),
        'max_attempts' => 3,
    ],
];
