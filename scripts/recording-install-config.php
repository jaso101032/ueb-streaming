<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(['root' => config('streaming.recordings_root'), 'token' => config('streaming.callback_token'),
    'project' => base_path(), 'ffmpeg' => config('streaming.ffmpeg'), 'ffprobe' => config('streaming.ffprobe'),
    'php' => PHP_BINARY], JSON_THROW_ON_ERROR);
