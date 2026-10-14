<?php

// Serve uploads from the disposable browser-test storage, never the developer's
// public/storage symlink. Production serves these PNGs directly through Nginx.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$storage = getenv('LARAVEL_STORAGE_PATH');
if (getenv('APP_ENV') === 'testing' && $storage && preg_match('~^/storage/(player-watermarks/[a-f0-9-]{36}\.png)$~D', $uri, $match)) {
    $path = $storage.'/app/public/'.$match[1];
    if (! is_file($path)) {
        http_response_code(404);

        return;
    }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    readfile($path);

    return;
}

return require __DIR__.'/../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
