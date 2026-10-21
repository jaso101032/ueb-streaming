# Notificaciones de UEBTV

La campana del encabezado funciona para usuarios con sesión iniciada y guarda
los últimos avisos en la base de datos. Cada usuario puede desactivar avisos de
directos o videos nuevos. El navegador **solo** recibe Web Push si el usuario
pulsa «Activar avisos del navegador» y concede el permiso; nadie puede forzar
esa autorización. Los visitantes sin cuenta no reciben avisos personales.

## Cuándo se envía un aviso

- Directo: cuando OBS inicia un evento, la señal sigue activa y ya existe una
  lista HLS con un segmento. Reintentos o reconexiones no duplican el aviso.
- Video programado: tras la conversión, cuando llega el turno real de emisión
  y el video puede reproducirse. Subir un archivo o dejarlo en cola no avisa.
- Grabación: después de publicar el MP4 final del evento. Una emisión puede
  generar un aviso de directo y, más tarde, uno de grabación disponible.
- La rotación de publicidad y los bucles del canal 24/7 **no** generan avisos.

Las tablas `audience_announcements`, `notifications` y `push_subscriptions` y
las preferencias del usuario se crean con `php artisan migrate`. El worker de
Laravel (`queue:work`, ya presente en el Compose) distribuye los avisos en
lotes. No detenerlo. La campana consulta novedades cada minuto mientras la
página está visible; Web Push funciona sin la página abierta, sujeto a las
restricciones del navegador y del sistema operativo. Al cerrar sesión se
revocan las suscripciones de esa cuenta; habrá que reactivarlas al ingresar.

## Configuración Web Push

En un entorno HTTPS de producción, genera **una sola vez** un par VAPID y
guarda las claves en el gestor de variables del despliegue, no en Git:

```bash
php -r 'require "vendor/autoload.php"; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());'
```

Asigna los valores a `WEB_PUSH_PUBLIC_KEY` y `WEB_PUSH_PRIVATE_KEY`; configura
`WEB_PUSH_SUBJECT` con `mailto:correo-institucional@ejemplo.edu.ec`. Las tres
variables llegan a `app` y `queue` a través de `docker-compose.production.yml`.
Mantén el mismo par entre despliegues: cambiarlo invalida las suscripciones
anteriores. Si faltan, la campana sigue funcionando y oculta la opción Push.

Prueba de aceptación: entra con una cuenta, abre la campana, activa los avisos,
permite las notificaciones, inicia un directo OBS y confirma que tras aparecer
el primer segmento HLS llega **un solo aviso**. Repite con otro dispositivo;
publica un video programado y verifica que el aviso llegue al entrar en emisión,
no durante su procesamiento. Prueba también desactivar preferencias y cerrar
sesión. En iPhone, Web Push requiere añadir el sitio a la pantalla de inicio.
El sitio incluye un manifiesto de instalación (`public/site.webmanifest`) en
modo independiente para ese caso.
El APK Android/TV no incluye aún notificaciones nativas: eso es una integración
independiente, no queda cubierto por este servicio web.
