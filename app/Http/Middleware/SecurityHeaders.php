<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las páginas.
 *
 * Content-Security-Policy: solo se ejecuta código y se cargan recursos del
 * propio sitio (no hay scripts ni fuentes externas). `blob:` es necesario para
 * el reproductor HLS (hls.js usa un worker y fuentes de medios en memoria).
 * Los estilos en línea se permiten porque React fija algunos tamaños y
 * fondos (miniaturas) con el atributo style.
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; "
        ."font-src 'self' data:; "
        ."media-src 'self' blob:; "
        ."connect-src 'self'; "
        ."worker-src 'self' blob:; "
        ."manifest-src 'self'; "
        ."frame-src 'none'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        // Durante el desarrollo con `npm run dev`, Vite sirve los scripts desde otro puerto.
        if (! is_file(public_path('hot'))) {
            $headers->set('Content-Security-Policy', self::CSP, false);
        }
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=(self), picture-in-picture=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // HSTS solo cuando la conexión ya es HTTPS (en producción, detrás del proxy).
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
