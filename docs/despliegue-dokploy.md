# Despliegue de UEBTV en Dokploy

La configuración de producción usa el archivo `docker-compose.production.yml`.
Incluye Laravel/React, PostgreSQL, RTMP/HLS, worker de cola, optimización de
videos y preparación de grabaciones. Los volúmenes Docker conservan base de
datos, archivos, grabaciones y segmentos entre despliegues.

## Crear el servicio

En el proyecto `UEBTV`, crea un servicio **Compose** con tipo **Docker Compose**
y proveedor **GitHub**. Selecciona `jaso101032/ueb-streaming`, rama `main` y el
archivo `docker-compose.production.yml`. La ruta de construcción es la raíz
del repositorio.

En la pestaña de dominios del servicio Compose, crea el dominio HTTPS para el
servicio `app`, puerto interno `8080`, con TLS/Let's Encrypt habilitado. Crea
un registro DNS `A` que apunte ese dominio a la IP pública del servidor.
El HLS se sirve por `https://TU_DOMINIO/hls/...` detrás del mismo dominio;
Nginx lo lee del volumen compartido con RTMP, sin reenviar cada segmento al
contenedor de ingesta.
Los MP4 privados se autorizan en Laravel y Nginx los entrega mediante rutas
internas `X-Accel-Redirect`, incluidas las solicitudes por rangos. Esto evita
mantener ocupado un proceso PHP por cada descarga o espectador. El Compose
activa `STREAMING_X_ACCEL_MEDIA=true`; en desarrollo sin ese Nginx se usa la
respuesta de archivo de Laravel. Las rutas `/_protected_media/` no son públicas.
Nginx y los procesadores de medios corren como `www-data` para leer los MP4
privados sin abrir sus permisos al resto de usuarios. El Dockerfile valida esta
configuración durante la construcción de la imagen y da acceso a `www-data` al
directorio temporal de Nginx, necesario para subir videos grandes.

Después de desplegar, comprueba un anuncio activo con una solicitud parcial,
por ejemplo `curl -r 0-1023 -o /dev/null -w '%{http_code}\n'` seguido de la URL
`https://TU_DOMINIO/senal/publicidad/ID/video`. Debe responder **206**, no **403**.
Un `403` del Nginx indica que el trabajador no puede leer el archivo. Un `404`
indica que el anuncio no está activo, no terminó de procesarse o ya no existe.

No hay un cupo de dispositivos configurado en el reproductor. La capacidad
depende sobre todo del bitrate de la señal, del ancho de banda saliente y del
servidor: como estimación inicial, `espectadores × bitrate` da el tráfico de
salida, al que hay que añadir margen para picos y otras peticiones. Antes de
prometer una cantidad concreta, probar 5, 10 y 20 dispositivos en redes
distintas y vigilar tiempos de respuesta, CPU, memoria, tráfico y cortes. El
autoplay se intenta silenciado; si el navegador bloquea incluso eso, exige un
toque del espectador y ninguna página web puede saltarse esa restricción.

El reproductor tolera demoras temporales de descarga antes de reiniciar la
señal: un MP4 programado o publicitario mantiene su solicitud en curso hasta
30 segundos sin avance y un directo HLS intenta reanudar la carga antes de
reconectarse. Esto evita que una red lenta provoque reinicios repetidos, pero
no sustituye el ancho de banda necesario. Si varios dispositivos se detienen
a la vez, comprueba primero el tiempo de respuesta y las respuestas `206` de
los MP4, o la descarga de la lista `.m3u8` y los segmentos `.ts` del directo;
revisa también tráfico saliente, CPU y memoria del servidor. Si solo falla un
dispositivo, comprueba su red, el ahorro de datos y las restricciones de
reproducción automática del navegador. Una prueba real con OBS y varios
espectadores sigue siendo necesaria después de cada despliegue.

## Variables de entorno y secretos

Define estas variables en el servicio Compose de Dokploy; no las guardes en
Git ni las pegues en chats:

| Variable | Valor |
| --- | --- |
| `APP_KEY` | Salida de `php artisan key:generate --show`, generada localmente. |
| `APP_URL` | URL HTTPS del dominio, por ejemplo `https://tv.ejemplo.edu.ec`. |
| `APP_TIMEZONE` | Zona horaria institucional; para Ecuador, `America/Guayaquil` (predeterminada). |
| `STREAMING_RTMP_URL` | `rtmp://tv.ejemplo.edu.ec:1935/live` (usa el host/IP público). |
| `STREAMING_CALLBACK_TOKEN` | `openssl rand -hex 32` (64 caracteres hexadecimales). |
| `DB_PASSWORD` | Contraseña aleatoria fuerte; puedes generar una con `openssl rand -hex 32`. |
| `DB_DATABASE` | `uebtv` o el nombre de base de datos que prefieras. |
| `DB_USERNAME` | `uebtv` o el usuario que prefieras. |
| `STREAMING_MEDIA_QUOTA_GB` | Opcional: cupo asignado a los medios UEBTV en GiB, por ejemplo `30`. No lo configures sin confirmar el límite real del host. |
| `WEB_PUSH_SUBJECT` | Opcional: `mailto:correo-institucional@ejemplo.edu.ec` para avisos del navegador. |
| `WEB_PUSH_PUBLIC_KEY`, `WEB_PUSH_PRIVATE_KEY` | Opcionales: par VAPID persistente. Generación y comprobación en [notificaciones](notificaciones.md). Sin ellas, solo funciona la campana interna. |
| `MAIL_MAILER` | `smtp` para enviar correos reales. El valor predeterminado `log` no entrega mensajes. |
| `MAIL_HOST`, `MAIL_PORT` | Servidor SMTP y puerto proporcionados por el proveedor del correo (habitualmente 587 o 465). |
| `MAIL_SCHEME` | Usa `smtps` cuando el proveedor exija SSL/TLS implícito en el puerto 465. Para 587 con STARTTLS, déjalo vacío. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Usuario (normalmente la dirección completa) y contraseña SMTP. Guárdalos solo como secretos en Dokploy. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Remitente autorizado por el proveedor y nombre visible, por ejemplo `notificaciones@tu-dominio` y `UEBTV`. |

La configuración del Compose obliga a definir las cinco variables secretas/de
conexión antes de desplegar. Usa los mismos valores de base de datos en ambos
servicios automáticamente; el servicio Postgres no debe exponerse públicamente.

### Correo para recuperar contraseñas

El formulario `/forgot-password` ya envía enlaces mediante el sistema de correo
de Laravel. Para habilitarlo en producción, configura las variables `MAIL_*`
anteriores en el servicio **Compose** de Dokploy y vuelve a desplegar para que
el contenedor reciba la configuración nueva. Comprueba con el proveedor SMTP el
host, puerto, tipo de cifrado, usuario, contraseña y dirección de remitente
permitida; crear un buzón por sí solo no activa el envío desde la aplicación.
No uses `localhost` como `MAIL_HOST` salvo que haya un servidor SMTP dentro
del propio contenedor. Mantén `APP_URL` con el dominio HTTPS público para que
el enlace de recuperación apunte a la web correcta.

Prueba con una cuenta de la plataforma cuyo correo puedas consultar: abre
`/forgot-password`, solicita el enlace y verifica que llega, que apunta al
dominio correcto y que permite cambiar la contraseña. Revisa también la
carpeta de spam. Si no llega, consulta los registros de `app` en Dokploy sin
publicar la contraseña SMTP ni enlaces de recuperación. Para mejorar la
entrega, configura SPF, DKIM y DMARC siguiendo las indicaciones del proveedor
del dominio.

## Red y primer arranque

- Publica `1935/TCP` en el firewall del servidor para la entrada de OBS. El
  Compose publica ese puerto en el host; no publiques PostgreSQL ni el puerto
  interno HLS `8080` del contenedor RTMP.
- Despliega el Compose. La aplicación espera PostgreSQL, ejecuta migraciones y
  luego inicia Nginx/PHP. Los workers procesan las colas, videos programados y
  grabaciones.
- Los videos programados admiten hasta 500 MB; verifica que el volumen `app_storage`
  tenga espacio suficiente para originales y archivos procesados durante la
  conversión. Para grabaciones en vivo, el volumen de grabaciones también debe
  alojar temporalmente el FLV y el MP4 a la vez; tras validar el MP4, el FLV
  nuevo se elimina automáticamente.
- Crea o asigna la cuenta administradora después del primer despliegue siguiendo
  el procedimiento institucional; no ejecutes seeders de desarrollo en producción.
- Prueba el sitio HTTPS, la subida de un video pequeño y una emisión OBS corta.
  Confirma el estado en el panel, reproducción HLS, finalización y grabación.

## Emitir desde cualquier red (OBS remoto)

El servidor tiene IP privada (por ejemplo `192.168.21.8`); desde otra red OBS
agota el tiempo de conexión aunque todo funcione por dentro. Para emitir desde
cualquier equipo:

1. **Dirección pública.** Crea un registro DNS tipo A (por ejemplo
   `stream.tudominio.edu.ec`) hacia la IP pública de la institución. No uses el
   proxy de Cloudflare en ese registro: no transporta RTMP.
2. **Router/firewall.** Redirige `1935/TCP` desde la IP pública hacia el
   servidor (`192.168.21.8:1935`) y permítelo también en el firewall de
   Proxmox y de la VM. No abras `8080`, PostgreSQL ni `/control`.
3. **Dokploy.** Define `STREAMING_RTMP_URL=rtmp://stream.tudominio.edu.ec:1935/live`
   y vuelve a desplegar. El panel mostrará esa dirección a los operadores.
4. **Token.** Genera uno nuevo con `openssl rand -hex 32` para
   `STREAMING_CALLBACK_TOKEN` (cámbialo si apareció en capturas o registros).
5. **Prueba externa.** Desde otra red (por ejemplo datos del teléfono) ejecuta
   `Test-NetConnection stream.tudominio.edu.ec -Port 1935` en Windows o
   `nc -zv stream.tudominio.edu.ec 1935` en Linux; debe conectar. En OBS usa
   servicio **Personalizado**, servidor `rtmp://stream.tudominio.edu.ec:1935/live`
   y la clave `ueb_...` del evento.

Con el puerto abierto a internet, la clave de emisión es la única credencial de
publicación: trátala como secreto y no cambies a una clave reutilizable.
Si un operador la comparte por error, genera otra antes de que empiece la
grabación del evento.

Dokploy gestiona dominios de Compose desde su pestaña **Domains** y reinicia el
Compose al guardar cambios de dominio. Asegúrate de respaldar PostgreSQL y los
volúmenes de medios; desplegar una imagen nueva no sustituye esos respaldos.

La [marca de agua configurable](marca-de-agua.md) usa la tabla `player_watermarks`
y PNG en `storage/app/public/player-watermarks`. El contenedor ya incluye GD,
ejecuta la migración y crea `public/storage`. Conserva el volumen `app_storage`
junto con la base de datos para no perder la selección al volver a desplegar.

## Subtítulos automáticos

El servicio `subtitles` transcribe el audio de los videos almacenados con
[faster-whisper](https://github.com/SYSTRAN/faster-whisper) (Whisper de código
abierto) y genera subtítulos en español que el público activa con el botón
**CC** del reproductor. Se aplica a grabaciones de directos ya terminados,
videos programados y publicidad institucional. **El directo no se transcribe.**

- No tiene costo por minuto ni envía el audio a servicios externos: el modelo
  `small` (~480 MB) viene incluido en la imagen.
- Procesa un video a la vez, con prioridad baja, para no afectar a la web ni a
  las conversiones. Como referencia, en 2 núcleos una hora de video puede tardar
  entre 30 y 60 minutos.
- Nunca reemplaza subtítulos subidos por el personal. Si alguien quita unos
  subtítulos automáticos, no se vuelven a generar solos; se pueden pedir de
  nuevo con **Generar subtítulos automáticos** en la gestión del evento o de la
  publicidad.
- Al desplegar esta versión, los videos que ya existían y no tienen subtítulos
  también entran en la cola.

Variables opcionales:

| Variable | Predeterminado | Uso |
|---|---|---|
| `STREAMING_AUTO_SUBTITLES` | `true` | `false` desactiva la generación y oculta sus botones. |
| `STREAMING_SUBTITLES_THREADS` | `2` | Hilos de CPU que usa Whisper. |
| `STREAMING_SUBTITLES_CPUS` | `2` | Límite de CPU del contenedor. |
| `STREAMING_SUBTITLES_MEMORY` | `3g` | Límite de memoria del contenedor. |

Para revisar el trabajador: `docker compose logs -f subtitles`.

En desarrollo local, instala faster-whisper en un entorno virtual, apunta
`STREAMING_SUBTITLES_PYTHON` a su `python` y ejecuta
`php artisan subtitles:generate --watch` en otra terminal.

## Calidad de video y espacio en disco

### Cómo se procesa cada video

| Origen | MP4 optimizado | Calidades adaptativas |
|---|---|---|
| Video subido (programación) | Se reduce a `STREAMING_MAX_HEIGHT` (1080p por defecto) sin deformarse | 1080p, 720p, 480p y 360p |
| Grabación de un directo (OBS) | Igual: un directo en 2K queda en 1080p | 1080p, 720p, 480p y 360p |
| Publicidad institucional | Igual | No: son clips cortos y se quedan en MP4 |
| Directo | No se transcodifica: llega tal como lo envía OBS | No |

- Solo se generan las calidades que no superan la altura del video: un video
  720p tiene 720p, 480p y 360p. Nunca se agranda un video.
- Video H.264 con calidad constante (CRF 23) y un tope de velocidad por
  calidad: 1080p 5 Mbps, 720p 2,8 Mbps, 480p 1,4 Mbps y 360p 0,8 Mbps. Una clase
  con diapositivas pesa mucho menos que el tope; un video con mucho movimiento
  no lo supera.
- El reproductor elige la calidad según la conexión y el tamaño de la
  pantalla, y el público puede cambiarla con el selector **Auto** del
  reproductor. Si las calidades fallan, sigue el MP4 desde el mismo punto.
- El servicio `renditions` las prepara de una en una, con prioridad baja, justo
  después de que el video queda listo. Mientras tanto se reproduce el MP4. Al
  desplegar esta versión, los videos existentes también entran en la cola.

| Variable | Predeterminado | Uso |
|---|---|---|
| `STREAMING_MAX_HEIGHT` | `1080` | Altura máxima del MP4 (720 ahorra espacio). |
| `STREAMING_ADAPTIVE_QUALITY` | `true` | `false` desactiva las calidades; se usa solo el MP4. |
| `STREAMING_RENDITIONS_PRESET` | `fast` | Velocidad de FFmpeg: `veryfast` tarda menos y pesa algo más; `medium` al revés. |
| `STREAMING_RENDITIONS_THREADS` | `0` | Hilos de FFmpeg (0 = todos los disponibles). |
| `STREAMING_RENDITIONS_CPUS` | `4` | Límite de CPU del contenedor. |

### Espacio en disco

Por cada video se guardan, por defecto:

1. El **original** (política de conservación, `STREAMING_KEEP_SOURCES=true`).
2. El **MP4 optimizado** (respaldo y descargas).
3. Las **calidades adaptativas**: en total, alrededor de 1,5 a 2 veces el MP4.

Como referencia, una hora de clase en 1080p puede ocupar entre 1 y 3 GB de MP4,
más 2 a 5 GB de calidades, más el original. Para ahorrar espacio:
`STREAMING_KEEP_SOURCES=false` (no se podrá volver a convertir desde el
original) o `STREAMING_MAX_HEIGHT=720`. Con `STREAMING_MEDIA_QUOTA_GB` el panel
muestra el uso respecto al espacio asignado (incluye las calidades). Si el
disco no tiene espacio suficiente, las calidades no se generan y se sigue
usando el MP4.

### Configuración recomendada de OBS

El directo no se transcodifica, así que su calidad y su peso dependen de OBS
(**Ajustes → Salida** y **Ajustes → Vídeo**):

- Resolución de salida: **1280×720** (o 1920×1080 si la conexión de subida lo permite).
- 30 cuadros por segundo.
- Codificador x264 o el de la tarjeta gráfica, control de frecuencia **CBR**.
- Tasa de bits de video: **2500–3500 kbps** en 720p; **4500–6000 kbps** en 1080p.
- Intervalo de fotogramas clave: **2 s**.
- Audio AAC a 128–160 kbps.

La subida de internet del lugar del evento debe ser al menos 1,5 veces la tasa
de bits elegida.
