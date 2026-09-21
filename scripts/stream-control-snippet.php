<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$token = config('streaming.callback_token');
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    fwrite(STDERR, "Configura STREAMING_CALLBACK_TOKEN con 64 caracteres hexadecimales y limpia la caché de configuración.\n");
    exit(1);
}
echo "            # UEBTV stream authorization\n";
echo "            on_publish http://127.0.0.1:8000/streaming/hooks/authorize?token={$token};\n";
echo "            on_update http://127.0.0.1:8000/streaming/hooks/authorize?token={$token};\n";
echo "            notify_update_timeout 3s;\n";
echo "            notify_update_strict on;\n";
