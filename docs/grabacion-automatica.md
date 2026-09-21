# Grabación y publicación automáticas

No hay revisión ni botón de aprobación: el usuario confirmó publicación automática. Nginx conserva el FLV desde el inicio y Laravel prepara un MP4 H.264/AAC al terminar. El original se mantiene. La sala pública da prioridad a ese MP4 y usa `video_url` como alternativa si no está disponible. El campo manual no se sobrescribe.

## Activación en Debian

Los campos de la base de datos ya fueron migrados en el equipo de desarrollo. En otro despliegue ejecutar `php artisan migrate --force` con el usuario de la aplicación.

Como root, desde `/home/javier/ueb-streaming`:

```bash
bash scripts/activate-recordings.sh
```

El instalador amplía la configuración de cierre existente, comprueba Nginx, guarda una copia de seguridad y crea el temporizador `uebtv-recordings.timer`. Puede volver a ejecutarse. Requiere PHP, Python 3, FFmpeg con libx264/AAC, ffprobe y systemd. Las rutas y el usuario están limitados al despliegue local inspeccionado. El token se lee de `.env`/configuración Laravel y no se muestra.

**Activar antes de empezar un evento**. Una conexión OBS que ya estaba abierta debe detenerse y volver a iniciarse para usar los nuevos ajustes de grabación. No es posible recuperar video anterior a la activación. Laravel debe permanecer disponible en `127.0.0.1:8000`; al desplegar en producción configurar un servidor permanente en la dirección de los callbacks.

El aviso de PHP sobre `soap` duplicado es independiente. Si Laravel usa caché de configuración, actualizarla después de cambiar `.env`.

## Flujo

- `on_publish` autentica la clave, registra el inicio y marca el evento en vivo. `on_update` mantiene la última señal cada tres segundos.
- `record all`, `record_append on` y `record_lock on` conservan un FLV por clave. Una reconexión vuelve a añadir contenido a ese archivo, sin sobrescribir lo anterior ni incluir silencio por el tiempo sin señal. No cambiar resolución/formato de OBS a mitad del evento.
- Al desconectarse OBS, se esperan 90 segundos por si regresa. Es configurable con `STREAMING_RECONNECT_GRACE` (mínimo 15 segundos). Si no regresa, el evento se finaliza automáticamente. Un callback de desconexión atrasado no interrumpe una sesión más reciente.
- «Finalizar transmisión» corta la publicación y bloquea reconexiones inmediatamente; la preparación espera también el margen de cierre. No se vuelve a abrir ese evento cambiando su estado ni regenerando la clave.
- El temporizador revisa cada 15 segundos cuando el proceso anterior termina. Se procesa un video a la vez, fuera de las peticiones web. El tiempo de conversión depende de duración, resolución y CPU.
- Se respeta el bloqueo POSIX de Nginx antes de leer. FFmpeg genera un archivo temporal, ffprobe valida video/duración y sólo entonces se publica. Las peticiones de rango HTTP permiten adelantar el video.
- Los fallos conservan el original, se reintentan hasta tres veces y se muestran en el detalle del evento. Admin y operador pueden pulsar «Reintentar preparación» después de resolver la causa. Un proceso interrumpido puede retomarse por el temporizador. No se publican archivos incompletos.
- La sala abierta comprueba cambios cada 15 segundos para mostrar el video cuando esté listo; respeta un mensaje de chat sin enviar.

## Almacenamiento y operación

- Originales privados: `/var/lib/uebtv/recordings/raw/CLAVE.flv`.
- MP4 privados: `/var/lib/uebtv/recordings/vod/UUID.mp4`, servidos por `/eventos/ID/grabacion` sólo cuando están listos y el evento finalizado.
- Directorios separados del almacenamiento temporal HLS; ninguna tarea borra los originales automáticamente.
- La clave de OBS no se puede cambiar después de iniciar: identifica el archivo conservado.
- El proceso usa el propietario del proyecto (javier), no root. Nginx escribe como www-data. El instalador establece permisos sólo en los directorios dedicados.
- Reservar disco para el original y el MP4; la aplicación no borra grabaciones antiguas para hacer espacio. La eliminación administrativa del evento lo oculta pero conserva los archivos para recuperación.

Comprobaciones del administrador:

```bash
systemctl status uebtv-recordings.timer
journalctl -u uebtv-recordings.service -n 50
systemctl start uebtv-recordings.service
```

El comando `php artisan streaming:prepare-recordings` también procesa los pendientes desde el usuario de la aplicación. Un bloqueo local impide ejecutar dos preparaciones simultáneas; esta implementación es para un único servidor de grabación.

## Pruebas

`php artisan test --filter=AutomaticRecordingTest` cubre reconexión, señal activa, callbacks atrasados, recuperación, permisos, enlace alternativo y conversión real FLV→MP4 con audio y solicitudes HTTP parciales.

`python3 tests/integration/recording_smoke.py` es una prueba opcional con SQLite temporal, Nginx y OBS simulado por FFmpeg en puertos 19350, 18091 y 18092. No utiliza la base de datos real ni modifica Nginx del sistema. La ejecución integral quedó pendiente en el entorno del asistente por restricciones de red y rechazo de la revisión automática de permisos; no se presenta como verificada.

Referencia oficial de Nginx: https://github.com/arut/nginx-rtmp-module/wiki/Directives#record_append y #record_lock.

## Audio del contenido en OBS

Desactivar Micrófono/Auxiliar. Para un navegador o reproductor externo, capturar Audio del escritorio o una Captura de salida de audio (PulseAudio) de la salida utilizada, evitando capturar el mismo dispositivo dos veces. Si el archivo se añade como Fuente multimedia en OBS, usar el audio de esa fuente. No reproducir la sala UEBTV con sonido en la misma salida capturada: se produciría realimentación. La grabación conserva la mezcla enviada por OBS, no puede separar después micrófono y contenido ya mezclados.

Referencia: https://obsproject.com/kb/audio-sources
