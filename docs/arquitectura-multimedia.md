# Arquitectura multimedia de UEBTV

## Protocolos utilizados

| Tramo | Protocolo | Transporte | Uso |
| --- | --- | --- | --- |
| OBS al servidor | RTMP, puerto 1935 | TCP | Ingesta autenticada de una emisión en directo. |
| Servidor al navegador | HLS sobre HTTP/HTTPS | TCP | Reproducción pública mediante listas `.m3u8` y segmentos. |
| Sala, chat y administración | HTTP/HTTPS | TCP | Datos de la plataforma y comentarios. |

La plataforma web no usa UDP. No es necesario para la entrega a navegadores: HLS usa HTTP/TCP. SRT/UDP sería una posible mejora futura para la **entrada** desde sedes remotas con redes inestables, no para sustituir HLS en el navegador.

## Formato de entrega y compatibilidad

El formato de salida de los archivos optimizados es MP4 con video H.264/AVC y audio AAC estéreo. Se utiliza `yuv420p`, que evita incompatibilidades frecuentes de color en navegadores, teléfonos y televisores. El índice MP4 se coloca al inicio (`faststart`) para comenzar la reproducción antes de descargar el archivo completo.

Los videos programados aceptan MP4/M4V, MOV, MKV, WebM, AVI, WMV y MPEG/MPG. FFprobe comprueba la duración y una pista de video; FFmpeg genera una salida única compatible con:

- Máximo 1920 × 1080, sin ampliar videos más pequeños.
- H.264 `libx264`, CRF 23, preset `fast`, dos hilos.
- AAC estéreo a 128 kb/s.

Las grabaciones de directos se preparan al finalizar con H.264, AAC a 160 kb/s y MP4 `faststart`. Es un proceso en segundo plano, nunca dentro de la petición web.

## Directos y latencia

Nginx recibe RTMP y genera HLS. El reproductor usa el elemento HTML `video` y hls.js cuando el navegador no reproduce HLS de forma nativa. El reproductor mantiene búfer corto y se sincroniza al borde en vivo.

Para evitar que una interrupción breve obligue a recargar toda la página, el reproductor intenta recuperarse de errores HLS y de una reproducción que deja de avanzar: espera progresivamente entre intentos, reconstruye la conexión y ofrece «Reconectar ahora». No reconecta una pausa solicitada por el espectador. El objetivo de sincronización se ajustó de uno a dos fragmentos de 2 s y se amplió el margen de búfer; esto favorece la continuidad, pero la latencia efectiva **debe medirse** y puede superar cinco segundos. No hay cambio automático de calidad: por ahora cada directo tiene una sola representación H.264/AAC.

La meta operativa es una latencia de hasta cinco segundos, pero no debe declararse cumplida sin una medición real OBS → dispositivo. Antes del despliegue se prueba con un reloj visible en la escena de OBS, al menos en PC, Android y una red distinta. Se registra mediana, percentil 95, reconexión y pérdida de audio/video.

La ventana HLS larga se usa para permitir retroceso temporal. Si la prioridad institucional es estrictamente menor o igual a cinco segundos, se debe limitar o desactivar ese DVR y ajustar fragmentos/lista HLS después de las pruebas. Una calidad automática tipo YouTube requiere una salida HLS multicalidad generada por FFmpeg; todavía no está implementada.

La selección de calidad tampoco existe para grabaciones o videos programados: se genera un solo MP4 optimizado por archivo. Para ofrecer «Automática», 1080p, 720p, etc., habría que producir varias representaciones (sin ampliar la resolución original), una lista maestra HLS y controles manuales. En directo, cada representación adicional consume CPU y puede aumentar la latencia; primero se medirá el sistema real y luego se elegirán perfiles sostenibles. No se debe prometer una resolución concreta si el video fuente o el ancho de banda no la permiten.

## Perfil OBS institucional provisional

Mientras no exista transcodificación multicalidad en servidor, OBS debe emitir H.264 + AAC, 48 kHz, estéreo, 30 fps, intervalo de fotogramas clave de 2 s y una resolución/bitrate validada contra el ancho de banda de subida real. La emisión no debe cambiar resolución ni códec durante un directo, porque afectaría HLS y a la grabación.

## Diagnóstico de cortes en producción

Que OBS no registre fotogramas perdidos descarta menos de lo que parece: confirma que no observó pérdidas de red de salida, pero un espectador todavía puede recibir tarde los segmentos HLS o no sostener el bitrate único. Ante un corte, anotar la hora y comprobar en este orden:

1. En **Estadísticas de OBS**, registrar bitrate real, FPS, pérdidas de red, fotogramas omitidos por codificación y retraso de renderizado. Confirmar el intervalo de fotogramas clave de 2 s.
2. En el dominio público, medir desde una PC y un teléfono en redes distintas el tiempo y código HTTP de la lista `.m3u8` y de varios segmentos `.ts` consecutivos. Comprobar si la lista avanza y si aparecen respuestas 404, 5xx o tiempos mayores que la duración del segmento. No compartir la clave RTMP.
3. En el servidor, revisar carga de CPU, RAM, disco de grabación, tráfico saliente y logs de Nginx/RTMP en la misma ventana horaria. La grabación y cada espectador consumen recursos aunque OBS esté estable.
4. Repetir con un perfil OBS moderado y estable; comparar cortes, calidad visible y retraso real antes de aumentar el bitrate. Si el problema afecta sobre todo a redes móviles, priorizar implementar varias calidades HLS, no solo aumentar el búfer.

La recuperación del cliente evita muchas recargas manuales, pero no puede reparar un origen que deja de generar segmentos, una salida del servidor saturada o una conexión del espectador insuficiente.

## Chat y archivo

Cada mensaje de un vivo guarda el segundo transcurrido desde el inicio de la transmisión. Tras finalizar, el servidor cierra el envío de mensajes y conserva el historial. En la grabación, el espectador puede pulsar la marca de tiempo para ir a ese momento del video.

## Licencias y atribución

- El reproductor visual es código propio React sobre el elemento `video` nativo del navegador.
- hls.js se distribuye bajo Apache License 2.0. Su aviso y texto de licencia deben conservarse en la distribución del proyecto.
- FFmpeg se utiliza como herramienta de servidor. Su licencia efectiva depende de cómo fue compilado: puede ser LGPL 2.1+ o GPL 2+ si incorpora componentes GPL. Antes del despliegue se debe registrar la salida de `ffmpeg -L` y conservar los avisos aplicables.
- OBS Studio se utiliza para producir la señal, no se incorpora dentro de la plataforma web.

## Operación pendiente de infraestructura

- Activar el servicio de grabación automática en el servidor definitivo.
- Medir la latencia real y documentar el perfil OBS validado.
- Implementar HLS multicalidad si se exige selección automática de calidad.
- Incorporar únicamente cortinas y publicidad institucional proporcionadas o autorizadas por la UEB para completar los huecos de la programación 24/7.
