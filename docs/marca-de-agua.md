# Marca de agua del reproductor

## Uso del panel

Solo el **administrador** puede entrar en **Marca de agua** (`/admin/marca-de-agua`),
seleccionar una imagen, revisar las vistas previas sobre fondos claro y oscuro y
pulsar **Guardar marca de agua**. Puede volver al recurso incluido en el proyecto
con **Restaurar logo oficial**. Los operadores y espectadores no pueden modificarla.

La selección es global: aparece en la esquina superior derecha de los reproductores
de directos, grabaciones, contenido programado y publicidad. Se actualiza **al abrir
o recargar la página**; no interrumpe ni reinicia transmisiones abiertas. No cambia
los logos de la cabecera, las pantallas de espera ni la firma institucional UEB.
Es una superposición HTML: no altera/transcodifica los videos ni se incrusta en sus
descargas. Las ventanas nativas de imagen en imagen no muestran superposiciones HTML.

## Guía de imágenes admitidas

- PNG **estático con transparencia real**, hasta **2 MB**. No se admiten JPG,
  WebP, SVG, GIF ni PNG animado (APNG). Cambiar la extensión no cambia el formato.
- Ancho de **128 a 2048 px**, alto de **64 a 2048 px**. Proporción cuadrada u
  horizontal, desde **1:1 hasta 6:1**. Recomendado: **768 × 384 px**.
- Usar exclusivamente recursos institucionales oficiales o autorizados, sin
  redibujar la identidad. Preferir la variante blanca o con contorno autorizado.
- Exportar sin fondo, con poco margen transparente. Una cuadrícula dibujada no
  equivale a transparencia. Revisar ambos fondos de la vista previa antes de guardar.

El servidor examina dimensiones antes de decodificar y comprueba todos los píxeles:
al menos 5 % deben ser prácticamente transparentes (alfa GD ≥ 120/127), al menos
la mitad del perímetro debe ser transparente y al menos 1 % debe ser visible
(alfa < 64/127). Así se rechazan logos opacos, vacíos o con un único píxel
transparente. **No es reconocimiento de imágenes**: un archivo que cumpla esas
reglas aún puede tener restos de fondo o contenido no institucional. La revisión
visual y la autorización siguen siendo responsabilidad del administrador.

La imagen se vuelve a codificar como PNG, sin conservar metadatos, nombre original
ni datos anexos. Se reduce proporcionalmente si supera 768 px de ancho, sin
cambiar colores, recortar ni reconstruir el logo. En el reproductor conserva su
proporción, una altura limitada y una sombra discreta; no se invierte en modo oscuro.

## Instalación, persistencia y despliegue

- Requiere **PHP GD con soporte PNG**. Ya está incluido en el Dockerfile del proyecto.
  En instalaciones manuales, comprobar `php -m` e instalar/habilitar GD si falta.
- Ejecutar `php artisan migrate`, `php artisan storage:link` y `npm run build` al
  actualizar. El arranque Docker ya ejecuta la migración y crea el enlace; el build
  compila React. No se necesitan nuevas variables de entorno ni servicios.
- Tabla `player_watermarks`: selección global (fila con ID 1); sin fila se utiliza
  `public/images/brand/uebtv-logo-final.png`.
- Archivos: `storage/app/public/player-watermarks/<uuid>.png`, servidos bajo
  `/storage/player-watermarks/`. Cada actualización tiene URL distinta para evitar
  caché obsoleta. Se debe conservar el volumen `storage` y respaldarlo junto con la BD.
- Los archivos anteriores se conservan para que las pestañas abiertas sigan mostrando
  su imagen. Restaurar la predeterminada no los borra. Si se requiere limpieza,
  respaldarlos primero y eliminar únicamente los no referenciados por la tabla,
  después de un periodo prudente para sesiones abiertas. No hay limpieza automática.
- Los archivos subidos por el admin no se versionan en Git. Un compañero recibe el
  logo predeterminado al clonar; para compartir una personalización entre instalaciones
  hay que transferir **la selección de BD y su archivo**, no rutas personales del PC.

## Código y comprobaciones

- Backend: `PlayerWatermarkController`, `PlayerWatermark`, `WatermarkImage` y migración
  `2026_10_13_120000_create_player_watermarks_table.php`.
- Panel React: `WatermarkSettings.jsx` y `watermark-admin.css`.
- Reproducción: `PlayerWatermark.jsx`, usado por `BrandedVideo` en `Player.jsx`.
  `UebtvMark.jsx` sigue usando exclusivamente la identidad fija del sitio.
- `php artisan test --filter=PlayerWatermarkTest`: permisos, persistencia,
  restauración, archivos inválidos, transparencia, normalización y errores de almacenamiento.
- Prueba de navegador en `tests/Browser/watermark.spec.js`: carga, rechazo, vista previa,
  restauración y diseño claro/oscuro en escritorio y móvil.
