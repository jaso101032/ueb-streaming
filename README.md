# UEBTV

Plataforma institucional de transmisiones de la Universidad Estatal de Bolívar. Permite programar eventos, emitir desde OBS mediante RTMP, reproducir la señal en HLS y publicar videos archivados en un catálogo tipo YouTube.

## Tecnologías

- Laravel 13 y PHP 8.3 o superior.
- PostgreSQL o SQLite, según el entorno.
- React 19 para toda la interfaz, Vite y estilos CSS/Tailwind.
- Nginx con módulo RTMP para recibir OBS y generar HLS.

## Requisitos

- PHP 8.3+ con extensiones requeridas por Laravel.
- Composer, Node.js y npm.
- PostgreSQL configurado, o SQLite para desarrollo local.
- Nginx con `ngx_rtmp_module` para streaming.
- OBS Studio en el equipo emisor.

## Instalación

Desde la raíz del proyecto:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install --ignore-scripts
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
```

Configura la base de datos en `.env` antes de ejecutar las migraciones. Nunca subas `.env` al repositorio.

## Ejecución local

Para permitir conexiones desde otros dispositivos de la red:

```bash
bash scripts/serve-local.sh
```

Consulta la IP vigente del servidor:

```bash
ip -4 addr show
```

Abre desde otro dispositivo:

```text
http://IP_DEL_SERVIDOR:8000/login
```

El comando completo para iniciar Laravel, la cola, los logs, Vite y el
procesador que optimiza los videos programados es:

```bash
composer run dev
```

El procesador queda atento a nuevas cargas mientras esta terminal siga
ejecutándose. No necesitas iniciar `php artisan scheduled:process --watch`
por separado; al cerrar `composer run dev`, también se detiene.

Para compartir el sitio por IP sin depender del servidor HMR de Vite, detén
el entorno de desarrollo y ejecuta `composer run share`. Este comando compila
los recursos de React y CSS para servirlos desde Laravel por el puerto 8000.
Los cambios de interfaz requieren volver a ejecutar el comando para compilar.

Vite también debe ser accesible desde la red local para que el navegador del otro dispositivo cargue React y los estilos; el proyecto detecta la IP local al iniciar. Si hay varias interfaces de red, puedes indicar la IP correcta con `VITE_HMR_HOST=192.168.1.25 composer run dev`. Permite los puertos TCP `8000` y `5173` en el firewall. La IP es dinámica y no debe escribirse como valor permanente en el código. Si DHCP cambia la IP, abre la aplicación con la nueva dirección.

## Streaming con OBS

En OBS selecciona **Ajustes > Emisión > Personalizado**.

Servidor:

```text
rtmp://IP_DEL_SERVIDOR:1935/live
```

Clave: copia la clave generada para el evento desde `/streaming/control`.

Después de iniciar OBS, el operador puede comprobar el estado **Señal recibida** en el panel. El monitor consulta periódicamente si Nginx está actualizando el playlist HLS.

## Configuración Nginx RTMP/HLS

La configuración mínima esperada es:

```nginx
rtmp {
    server {
        listen 1935;
        application live {
            live on;
            hls on;
            hls_path /tmp/hls;
            hls_fragment 3s;
            hls_playlist_length 60s;
        }
    }
}

http {
    server {
        listen 80;
        location /hls {
            root /tmp;
            types {
                application/vnd.apple.mpegurl m3u8;
                video/mp2t ts;
            }
            add_header Cache-Control no-cache;
            add_header Access-Control-Allow-Origin *;
        }
    }
}
```

El playlist se genera con este formato:

```text
http://IP_DEL_SERVIDOR/hls/CLAVE.m3u8
```

Puertos necesarios: `8000` para Laravel, `80` para HLS y `1935` para RTMP.

## Flujo de eventos

1. Un administrador u operador crea un evento en `/admin/eventos/create`.
2. El sistema genera una clave única para OBS.
3. El operador copia servidor y clave desde `/streaming/control`.
4. OBS publica en `rtmp://IP_DEL_SERVIDOR:1935/live`.
5. Nginx genera el playlist HLS.
6. La sala pública reproduce la señal en `/eventos/{evento}`.
7. Al terminar, edita el evento y registra un enlace MP4 en **Video grabado**.
8. Los eventos finalizados con video aparecen en `/eventos?filtro=anteriores`.

## Videos archivados

El campo `video_url` permite guardar un enlace directo a un archivo compatible con el navegador, normalmente MP4:

```text
https://servidor.example/videos/conferencia-ueb.mp4
```

El evento debe estar finalizado o tener `video_url` para aparecer en el filtro de anteriores. Las miniaturas se pueden mostrar desde `storage` cuando estén configuradas.

## Roles

- `admin`: dashboard administrativo y control de streaming.
- `operador`: control de streaming y gestión operativa.
- `usuario`: acceso general a la plataforma.

Las rutas administrativas requieren autenticación y los roles `admin` u `operador`.

## Rutas principales

| Ruta | Uso |
| --- | --- |
| `/login` | Inicio de sesión |
| `/dashboard` | Redirección según rol |
| `/eventos` | Catálogo público |
| `/eventos/{evento}` | Sala de reproducción |
| `/admin/dashboard` | Panel administrativo |
| `/admin/eventos` | CRUD de eventos |
| `/streaming/control` | Credenciales y monitor OBS |
| `/streaming/evento/{evento}/estado` | Estado JSON de señal HLS |

## Pruebas y validación

```bash
php artisan test
php artisan view:cache
npm run build
```

## Solución de problemas

### No se puede acceder desde otro equipo

- Comprueba la IP con `ip -4 addr show`.
- Inicia Laravel con `--host=0.0.0.0`.
- Comprueba que ambos equipos estén en la misma red y no en una red de invitados.
- Verifica los puertos `8000`, `80` y `1935`.
- Desactiva `AP Isolation` o `Client Isolation` en el router.

### OBS no conecta

- Usa la IP actual, no `localhost` ni `127.0.0.1`.
- Usa el puerto `1935`.
- Usa `/live` como aplicación.
- Regenera la clave y copia la nueva credencial.

### La página carga sin estilos

```bash
npm run build
php artisan view:clear
php artisan view:cache
```

Después recarga con `Ctrl + F5`. Si aparece `EACCES` al compilar:

```bash
sudo chown -R "$USER":"$USER" public/uebtv-build-v2
```

### La señal HLS no aparece

```bash
ss -ltnp | grep -E ':(80|1935)'
curl -I http://IP_DEL_SERVIDOR/hls/CLAVE.m3u8
```

## Estado del proyecto

UEBTV cuenta con catálogo público, salas de reproducción, soporte HLS/RTMP, biblioteca de videos archivados, panel administrativo, control para operadores, interfaz React y autenticación con roles en Laravel. Blade sirve únicamente el documento inicial y los datos de cada página.


## Gestión de eventos y miniaturas

Administradores y operadores pueden crear, consultar, editar y eliminar eventos desde `/admin/eventos`. Crear y editar comparten el formulario con categorías y facultades por nombre; las carreras se filtran por facultad y se comprueba su pertenencia en el servidor.

- El cierre es opcional, pero debe ser posterior al inicio.
- Las miniaturas aceptan JPG, PNG y WebP de hasta 2 MB y 4096 × 4096 píxeles. Se guardan en el disco público; `php artisan storage:link` habilita su visualización.
- Al editar sin seleccionar otra imagen se conserva la actual. Los campos opcionales omitidos en una petición se conservan; enviarlos vacíos los limpia.
- El enlace de grabación admite HTTP/HTTPS y hasta 255 caracteres, de acuerdo con la columna actual. Debe apuntar a un video reproducible directamente.
- Crear genera evento y transmisión en una transacción; el detalle administrativo muestra la confirmación y enlaces para editar, abrir la sala o controlar la transmisión.
- `php artisan db:seed` carga los catálogos de los seeders existentes y puede repetirse. No crea una cuenta de prueba. Revisa los nombres institucionales de esos seeders antes de usarlos en una instalación nueva.

Pruebas del módulo: `php artisan test --filter=EventoManagementTest`.

## Búsqueda y próximos eventos

El catálogo `/eventos` permite combinar `q` (título), `facultad_id`, `categoria_id` y `filtro` (`todos`, `en_vivo`, `anteriores`, `proximos`). Conserva los filtros al paginar y cambiar de estado. Los próximos son eventos programados con inicio futuro, ordenados del más cercano al más lejano; la sección destacada muestra hasta cuatro y respeta la búsqueda, facultad y categoría.

## Chat y moderación

Administradores y operadores pueden pulsar **Retirar** junto a un mensaje en la sala. La eliminación es lógica: se guardan la fecha y el usuario moderador; no se muestra el mensaje a los espectadores. Los usuarios normales no pueden moderar y cada mensaje se valida contra su evento.

El chat conserva un máximo de 80 mensajes en cada respuesta. Usa una caché compartida de 3 segundos por evento y ETag para responder sin cuerpo si no cambió. Los envíos y la moderación invalidan esa caché. El navegador consulta cada 5 segundos, pausa las consultas al ocultar la pestaña y aumenta la espera hasta 30 segundos ante fallos. Las retiradas también se reflejan en las otras sesiones durante sus actualizaciones. No utiliza WebSockets y no se ha realizado una prueba de carga concurrente; estos cambios reducen consultas y transferencia, pero no certifican una capacidad de espectadores.

El formulario conserva el borrador ante errores y explica límites de envío, sesión vencida y fallos de conexión. No reintenta automáticamente los envíos para evitar duplicados.

En otros entornos, ejecutar `php artisan migrate` y `npm run build` antes de servir la versión nueva. La migración `2026_09_19_180000_add_chat_moderation` agrega los campos de moderación y un índice por evento e ID.

Comprobaciones específicas:

```bash
php artisan test --filter=CatalogAndChatTest
npm run test:browser
```


## Frontend React

Toda la interfaz de las rutas activas se renderiza con React: acceso y recuperación de cuenta, perfil, catálogo, sala de reproducción y comentarios, administración de eventos, streaming y contenido programado. Alpine y los scripts anteriores de manipulación del DOM fueron retirados.

- Entrada y registro de páginas: `resources/js/app.js`.
- Navegación, páginas y reproductores: `resources/js/platform/`.
- Panel y programador: `resources/js/components/`.
- Datos públicos y permisos de interfaz: `app/Support/ReactPage.php`.
- Estilos compartidos: `resources/css/react-platform.css`, `studio.css`, `watch-experience.css` y `scheduled-admin.css`.

Laravel mantiene rutas, sesiones, CSRF, validación, cargas de archivos y autorización. La navegación entre páginas y los formularios usan las rutas existentes; no se requiere un servidor Node en producción. Los archivos Blade son puntos de arranque con datos serializados, no contienen la interfaz. Los estilos siguen siendo CSS: React administra componentes e interacciones.

Después de actualizar el código:

```bash
npm ci
npm run build
php artisan view:clear
```

En el hosting hay que publicar también **todo** `public/uebtv-build-v2` (incluido el manifest y el fragmento HLS). Un commit por sí solo no compila los estilos. El reproductor HLS se descarga del propio build solo cuando se abre una emisión en directo.

### Pruebas de interfaz

```bash
npx playwright install chromium
npm run test:browser
```

Requieren PHP con SQLite y FFmpeg. El servidor de prueba usa el puerto 8017 y una base SQLite temporal en `/tmp`; no utiliza la base real. No ejecutes otra instancia de prueba en ese puerto. Si existe configuración Laravel cacheada, el script se detiene para evitar utilizar otra base de datos.

Las pruebas cubren navegación móvil, formularios y vistas previas, permisos, chat con errores de red, y sincronización de un MP4 de prueba. Para validar una emisión OBS real hace falta que Nginx/RTMP esté recibiendo señal.

## Canal automático y subida de videos

Abre `/senal` para ver la programación continua: el directo tiene prioridad y, al
terminar, el pregrabado continúa desde el punto interrumpido. En **Contenido
programado** se muestran la duración total y los minutos de video pendientes.

Para subir videos de hasta 500 MB localmente, detén el servidor anterior y usa
`bash scripts/serve-local.sh` o `composer run dev`. El PHP por defecto puede
limitar los archivos a 2 MB; `php artisan serve` sin configurar los límites no
los amplía. Más detalles en [contenido programado](docs/contenido-programado.md).
