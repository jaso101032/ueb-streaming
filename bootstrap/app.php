<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Dokploy terminates TLS at its reverse proxy; honor forwarded HTTPS headers.
        $middleware->trustProxies(at: '*');

        // Registrar el alias del middleware de roles
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'operator.permission' => \App\Http\Middleware\EnsureOperatorPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $exception, \Illuminate\Http\Request $request) {
            $limits = \App\Support\UploadLimits::scheduled();
            $message = 'La subida supera el límite del servidor. Máximo actual: '.$limits['maxMb'].' MB.';
            if ($request->expectsJson()) return response()->json(['message' => $message], 413);
            return response()->view('errors.upload-too-large', ['props' => [
                'maxMb' => $limits['maxMb'],
                'backUrl' => $request->is('admin/contenido-programado') ? route('admin.scheduled.index') : route('eventos.index'),
            ]], 413);
        });
    })->create();
