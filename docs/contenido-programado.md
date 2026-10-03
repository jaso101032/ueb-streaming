# Emisiones pregrabadas sincronizadas

Administración → Contenido programado permite subir MP4/M4V, MOV, MKV, WebM, AVI, WMV y MPEG/MPG (hasta 500 MB y 12 horas), título, descripción, categoría y fecha/hora en la zona indicada en el formulario. FFprobe valida la duración real. Los archivos permanecen en el disco local privado y solo se sirven desde el horario de inicio; eliminar el evento bloquea el acceso.

La reproducción no requiere cron, pero la conversión necesita el procesador `scheduled:process --watch` (incluido en el servidor local): cada sala consulta el reloj del servidor cada 5 segundos y calcula el punto correspondiente. Corrige desfases superiores a 1,5 segundos y retoma en el punto actual después de una pausa. Es sincronización aproximada de reproducción, no emisión RTMP ni sincronización exacta cuadro a cuadro. El navegador puede exigir un clic para iniciar con sonido. Cuando termina el horario, pasa a video bajo demanda desde el inicio. Se identifica siempre como pregrabado.

Para desarrollo local con subidas grandes, detener el servidor Artisan anterior y ejecutar:

```bash
bash scripts/serve-local.sh
```

En hosting configurar PHP `upload_max_filesize=500M`, `post_max_size=512M` y `max_input_time=600`, además de los límites del servidor HTTP. Ejecutar `php artisan migrate --force` y compilar con `npm run build`. Se requieren FFmpeg y FFprobe instalados y espacio suficiente. Los límites normales de PHP de 2 MB no sirven para subir videos grandes.

La programación creada es inmutable: para cambiarla, el administrador elimina el evento desde la gestión y programa uno nuevo. No se genera clave OBS. Los archivos se conservan tras el borrado lógico; no se realiza una purga física automática.

## Señal continua y prioridad del directo

`/senal` es el canal público automático; `/` abre ese canal. El catálogo incluye
«Ver señal continua». Las salas individuales siguen disponibles para comentarios
y reproducción bajo demanda.

La fecha elegida es el inicio mínimo: los videos se ordenan por fecha e ID y se
reproducen de uno en uno. No se repiten automáticamente al agotarse la lista.
Si hay un hueco entre horarios, se anuncia la próxima emisión. Un directo con
publicador activo interrumpe el contenido; al desconectarse o finalizar, el video
continúa desde el segundo interrumpido y se desplazan las emisiones siguientes.
Si hay varios directos, permanece el que empezó primero hasta que termine.

El servidor conserva intervalos de interrupción (se fusionan los solapados).
Los hooks de Nginx `publish`/`update_publish` renuevan la señal por 30 segundos;
`disconnected` y la finalización administrativa la cierran. Sin heartbeat, caduca
a los 30 segundos para evitar un canal bloqueado por una desconexión abrupta.
El navegador consulta cada 3 segundos y corrige su posición con el reloj del
servidor. El autoplay empieza silenciado por las restricciones del navegador.
No es una nueva salida RTMP: es un canal sincronizado para los espectadores web.

El panel suma la duración verificada con FFprobe de todos los videos disponibles
(no solo la página actual) y muestra el tiempo de video pendiente. Excluye los
cancelados y eliminados. Las pausas, esperas y directos no suman minutos de video.
Antes de subir, la vista previa muestra una duración orientativa del archivo.

Al desplegar: `php artisan migrate --force`, `npm run build` y publicar los assets.
Los hooks del servidor RTMP deben estar conectados como indica la configuración
existente de streaming; marcar manualmente un evento «en vivo» no acredita señal.

## Error 413 al subir un video localmente

PHP puede venir con `upload_max_filesize=2M` y `post_max_size=8M` aunque el
formulario permita archivos de 500 MB. Reinicia el servidor de desarrollo:

```bash
# Primero detén el servidor anterior con Ctrl+C en su terminal.
cd /home/javier/ueb-streaming
bash scripts/serve-local.sh
```

`composer run dev` también utiliza ahora ese script. Ejecuta PHP con un límite
por archivo de 500 MB, POST de 512 MB (incluye campos y multipart) y 600 segundos
para la subida. Estos límites se aplican al proceso del servidor sin editar el
php.ini global. No uses `php artisan serve` sin esa configuración para videos grandes.
El formulario consulta los límites reales del PHP que atiende la petición y
rechaza archivos demasiado grandes antes de subirlos. Un POST que sobrepase el
límite muestra una página de error 413 legible, sin la traza interna.
En hosting se deben ajustar los valores del PHP web y cualquier límite del proxy.

## Formatos y optimización automática

Se admiten MP4/M4V, MOV, MKV, WebM, AVI, WMV y MPEG/MPG, hasta 500 MB y
12 horas. FFprobe comprueba que exista una pista de video real antes de aceptar
la subida. La conversión se realiza fuera de la petición HTTP:

- MP4 con H.264 (CRF 23, preset fast), píxeles yuv420p y audio AAC estéreo 128 kb/s.
- Resolución máxima 1920 × 1080, conservando proporción y sin ampliar videos pequeños.
- Índice MP4 al principio (`faststart`) para empezar a reproducir antes de descargar todo.
- Dos hilos de codificación y una conversión por procesador para moderar el uso de CPU.

El resultado no siempre pesa menos: un original muy comprimido puede aumentar
al convertirse a un formato más compatible. El panel muestra ambos tamaños.
Los subtítulos incrustados y pistas de audio adicionales no se conservan; se usa
la primera pista de video y la primera de audio, si existe.

Los archivos pendientes no se emiten. Si la hora prevista pasa mientras se
optimizan, entran en la programación desde que estén listos, sin saltarse el inicio.
Después de verificar el resultado se elimina el original. Si falla la conversión,
se conserva para permitir «Reintentar», disponible únicamente al administrador.
Usa «Actualizar estado» en la cartelera para consultar el resultado.

Aplica la migración y reinicia el servidor local:

```bash
php artisan migrate
bash scripts/serve-local.sh
```

El script local y `composer run dev` arrancan también el procesador. En hosting
se debe mantener un proceso supervisado independiente (Supervisor/systemd):

```bash
php artisan scheduled:process --watch
```

Para procesar una tanda manualmente: `php artisan scheduled:process`.
Se necesitan FFmpeg con libx264/AAC y FFprobe instalados. Una conversión que exceda
una hora falla y puede reintentarse. Tras una interrupción del proceso, los trabajos
bloqueados se marcan como fallidos a los 65 minutos.
