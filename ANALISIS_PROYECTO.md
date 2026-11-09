# Diagnóstico de UEBTV

Actualizado el 9 de noviembre de 2026. Sustituye al diagnóstico del 8 de septiembre, que describía una versión anterior (Blade/Alpine, 29 pruebas).

## Resumen

Monolito **Laravel 13 / PHP 8.3** con interfaz **React 19** (Vite, Tailwind) servida desde vistas Blade, y una app Android WebView en `android/`. Streaming con OBS → nginx-rtmp → HLS, grabación automática a MP4 con ffmpeg y un canal 24/7 (`/senal`) que prioriza directo, programación, publicidad institucional y pantalla de espera. Despliegue con Docker Compose en Dokploy (`app`, `queue`, `scheduled-videos`, `recordings-processor`, `rtmp`, Postgres 17).

## Estado de las comprobaciones

- `php artisan test`: 148 pruebas; 131 pasan. Las 17 restantes son de `PlayerWatermarkTest` y requieren la extensión **GD**, que falta en el equipo local pero está instalada en la imagen Docker.
- `composer audit` antes de actualizar: 25 avisos en 8 paquetes (laravel/framework, league/commonmark, league/flysystem, symfony/*). Se actualizaron las dependencias dentro de las restricciones existentes; volver a ejecutar `composer audit` tras cada actualización.
- Livewire se retiró: no se usaba en el código.

## Hallazgos resueltos

- Dump SQL con datos reales y `uebtv-build-v2.tar.gz` dejaron de estar versionados y se ignoran en `.gitignore`.
- Las pruebas ya no dependen de `APP_LOCALE` del `.env` local (`phpunit.xml` fija `es`).
- `ext-gd` declarado en `composer.json`.
- Imagen RTMP fijada a `alfg/nginx-rtmp:v1.6.0` (equivale al `latest` anterior).
- `routes/web.php` ordenado: imports, `require auth.php` al final y la redirección de `/dashboard` en `DashboardRedirectController`.
- Resueltos desde septiembre: clave de emisión ya no se expone en las vistas públicas, el estado sigue a OBS mediante los hooks de nginx, `finalizar` solo cierra el evento si nginx confirma el corte.

## Pendiente

1. **Historial de git.** El dump SQL (usuarios, hashes, sesiones, tokens de reset y claves de emisión) sigue en commits anteriores. Si el repositorio salió de un entorno de confianza: reescribir el historial (por ejemplo con `git filter-repo`), invalidar sesiones y rotar contraseñas y claves de emisión.
2. `trustProxies(at: '*')` es aceptable solo si el puerto 8080 no está expuesto fuera de Dokploy.
3. `/control` de nginx se limita por IP privada; cualquier contenedor de la red puede cortar emisiones.
4. `ChannelSchedule::timeline()` carga todos los videos programados en cada consulta a `/senal/estado`; medir con carga y cachear unos segundos si hace falta.
5. Los procesos `scheduled:process --watch` y el bucle de grabaciones no tienen monitoreo.
6. Revisar si `scripts/install-*.py` y `scripts/activate-*.sh` siguen vigentes tras Docker.
7. Probar también contra PostgreSQL (las pruebas usan SQLite en memoria).
8. `public/descargas/uebtv.apk` está versionado por ser la descarga pública; considerar publicarlo como artefacto de release.
