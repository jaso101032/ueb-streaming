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
El HLS se sirve por `https://TU_DOMINIO/hls/...` detrás del mismo dominio.

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
  tenga espacio suficiente para originales y archivos procesados.
- Crea o asigna la cuenta administradora después del primer despliegue siguiendo
  el procedimiento institucional; no ejecutes seeders de desarrollo en producción.
- Prueba el sitio HTTPS, la subida de un video pequeño y una emisión OBS corta.
  Confirma el estado en el panel, reproducción HLS, finalización y grabación.

Dokploy gestiona dominios de Compose desde su pestaña **Domains** y reinicia el
Compose al guardar cambios de dominio. Asegúrate de respaldar PostgreSQL y los
volúmenes de medios; desplegar una imagen nueva no sustituye esos respaldos.
