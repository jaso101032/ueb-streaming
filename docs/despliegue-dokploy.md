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

La configuración del Compose obliga a definir las cinco variables secretas/de
conexión antes de desplegar. Usa los mismos valores de base de datos en ambos
servicios automáticamente; el servicio Postgres no debe exponerse públicamente.

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

Dokploy gestiona dominios de Compose desde su pestaña **Domains** y reinicia el
Compose al guardar cambios de dominio. Asegúrate de respaldar PostgreSQL y los
volúmenes de medios; desplegar una imagen nueva no sustituye esos respaldos.

La [marca de agua configurable](marca-de-agua.md) usa la tabla `player_watermarks`
y PNG en `storage/app/public/player-watermarks`. El contenedor ya incluye GD,
ejecuta la migración y crea `public/storage`. Conserva el volumen `app_storage`
junto con la base de datos para no perder la selección al volver a desplegar.
