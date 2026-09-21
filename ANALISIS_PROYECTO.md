# Diagnóstico de UEBTV

Fecha: 8 de septiembre de 2026. Revisión del árbol de trabajo actual, incluidos cambios preparados y sin preparar en Git.

## Alcance y comprobaciones

Revisión de estructura, rutas, controladores, modelos, migraciones, seeders, vistas principales, autenticación, frontend, configuración de compilación y pruebas. No se modificó lógica de aplicación ni se ejecutaron migraciones sobre la base de uso habitual. No se leyeron secretos de `.env`.

- `php artisan route:list --except-vendor`: 35 rutas registradas.
- `php artisan test`: 29 pruebas; 28 pasan y 1 falla. La prueba de portada consulta `eventos` sin preparar la base SQLite en memoria; falta `RefreshDatabase` en `tests/Feature/ExampleTest.php`.
- Comprobación `php -l` de archivos PHP en app, bootstrap, config, migraciones, seeders, rutas y tests: sin errores de sintaxis.
- `npm run build -- --outDir /tmp/uebtv-analysis-build-20260908`: correcto. Se utilizó una salida temporal para conservar los assets existentes. Vite instalado: 8.1.5; Node: 20.20.2.
- El manifiesto existente apunta a archivos presentes en `public/uebtv-build-v2/assets`.
- PHP avisa que SOAP está cargado dos veces; revisar configuración del entorno.

No se comprobó reproducción en navegador, emisión OBS real, configuración activa de Nginx, entrega de correo, despliegue HTTPS ni carga concurrente. La revisión de código no certifica esos servicios. No se realizó auditoría de vulnerabilidades de dependencias ni validación de datos de producción.

## Arquitectura y funcionamiento

Es un monolito Laravel: `composer.json` requiere PHP ^8.3, Laravel ^13.8 y Livewire ^4.4. La autenticación sigue la estructura de Breeze. Las pantallas actuales utilizan principalmente Blade, CSS propio y Tailwind 3, con Alpine.js. React 19 está incluido en el bundle y tiene un componente de bienvenida; Livewire está instalado, pero no se encontraron componentes de aplicación en uso.

| Módulo | Archivos principales | Función |
| --- | --- | --- |
| Entrada y permisos | routes/web.php, routes/auth.php, CheckRole.php | Portada, redirecciones según rol y protección de administración |
| Catálogo | EventoPublicoController.php, eventos/index.blade.php | Eventos no cancelados, filtros y paginación de 12 |
| Sala pública | eventos/show.blade.php | Reproducción HLS o MP4, chat y contenidos relacionados |
| Gestión | EventoController.php, admin/eventos/ | Crear, editar, consultar y eliminar eventos |
| Panel | Admin/DashboardController.php | Totales y cinco eventos recientes |
| Streaming | TransmisionController.php, streaming/index.blade.php | Claves OBS y comprobación del playlist local |
| Chat | ChatMessageController.php, ChatMessage.php | Lectura pública y escritura autenticada, máximo 180 caracteres |
| Perfil | ProfileController.php | Actualizar perfil, contraseña y eliminar cuenta |

Flujo: un administrador u operador crea un evento; una transacción crea también su transmisión y clave. OBS publica a Nginx RTMP, que debe generar HLS en `/tmp/hls`. El panel comprueba si el playlist se actualizó en los últimos 15 segundos. El reproductor público depende del estado `en_vivo` guardado en el evento. El video archivado se registra mediante una URL; no existe un proceso de grabación o transcodificación implementado en la aplicación.

La raíz abre el evento en vivo de fecha de inicio más reciente, o redirige al catálogo. `/dashboard` redirige administradores al panel, operadores al control y usuarios al catálogo. Ambos roles privilegiados pueden acceder al mismo CRUD completo.

## Datos

- `users`: identidad, contraseña, verificación y rol.
- `facultades` → `carreras`; también existe tabla `departamentos`.
- `eventos`: categoría, organizador, facultad y carrera opcionales, fechas, estado, imágenes y video. Usa borrado lógico.
- `transmisiones`: clave, URLs, indicador activo, espectadores y tiempos; el modelo declara una transmisión por evento.
- `chat_messages`: evento, usuario, mensaje y fechas; índice por evento y fecha.
- Tablas de sesiones, recuperación de contraseña, caché y colas.

## Hallazgos prioritarios

### Alta: integridad, acceso y funcionamiento

1. **Dump con datos de cuentas y sesiones versionado.** `database/ueb_streaming_db.sql` está rastreado por Git y contiene 5 filas de usuarios, 2 sesiones y 1 transmisión. Incluye columnas de hashes de contraseña, payload de sesión y clave de streaming. No se reproducen sus valores aquí. Evaluar exposición y vigencia, sustituir el dump por datos sintéticos y, si salió del entorno confiable, invalidar sesiones y rotar las credenciales afectadas. Quitar el archivo del último commit no elimina su historial.
2. **Editar puede perder datos existentes.** `EventoController::update()` escribe `facultad_id`, `carrera_id` y `fecha_fin` desde el request aunque el formulario de edición no envía esos campos; pasan a null. Preservar los campos ausentes o incorporarlos explícitamente al formulario.
3. **Detalle administrativo roto.** `EventoController::show()` devuelve `admin.eventos.show`, una vista inexistente. El listado incluye un enlace «Ver» a esa ruta. Crear el detalle o definir una redirección deliberada y probarla.
4. **Encabezados ocultos.** `layouts/app.blade.php` imprime `$slot`, pero no el slot `header`. El listado administrativo coloca allí su título y botón «Nuevo Evento»; tampoco se muestran otros encabezados que usan este patrón.
5. **Validación incompleta.** Crear y actualizar guardan fecha de fin y lugar sin validación; actualizar tampoco valida estado ni facultad/carrera. No se verifica coherencia temporal ni pertenencia de carrera a facultad. `video_url` acepta 2048 caracteres, mientras la migración define un string de longitud predeterminada, normalmente 255; PostgreSQL puede rechazar valores admitidos por la validación.
6. **El estado público no sigue a OBS.** `estado()` devuelve un diagnóstico del archivo, pero no actualiza el evento ni `transmisiones.activo`. Recibir señal no habilita automáticamente el reproductor de un evento programado; finalizar OBS tampoco cambia su estado. Definir el ciclo de vida y su integración con Nginx.
7. **Clave de emisión usada como identificador público.** La URL HLS expone la misma clave usada por OBS. El ejemplo Nginx del README no incluye autenticación de publicación. Falta comprobar la configuración activa; separar identificador público y credencial de publicación e integrar validación RTMP antes de considerar cerrado este flujo.
8. **Borrar una cuenta puede borrar eventos físicamente.** El perfil permite eliminar al usuario y la FK `organizador_id` tiene borrado en cascada. Esto puede eliminar sus eventos y registros dependientes, saltándose el borrado lógico de Eloquent. Definir conservación o reasignación institucional antes de habilitar ese uso.

### Media: experiencia y escalabilidad

9. **Instalación sin catálogos.** `DatabaseSeeder` no llama a `CategoriaSeeder` ni `FacultadCarreraSeeder`. La categoría es obligatoria al crear eventos. Además, los formularios piden un ID numérico en lugar de ofrecer categorías disponibles.
10. **Chat inicial sin límite en SQL.** La sala carga `chatMessages.user` completo y después recorta a 80 mensajes en memoria. El endpoint de actualización sí limita a 80 en SQL. Aplicar el límite también a la carga inicial.
11. **Chat con polling constante.** Cada visitante consulta cada dos segundos y descarga hasta 80 mensajes; no hay cursor, pausa de pestaña oculta, moderación o feedback de errores. Mil visitantes implicarían aproximadamente 500 peticiones por segundo solo para actualizar el chat. La escritura sí tiene límite de 30 peticiones por minuto.
12. **Streaming acoplado al mismo host y HTTP.** Las URLs se reconstruyen desde el host de la petición y no aprovechan las URLs guardadas. El monitor exige `/tmp/hls` en el servidor Laravel. Separar configuración de RTMP, URL pública HLS y ubicación local; verificar comportamiento HTTPS.
13. **Espectadores sin medición implementada.** Se muestra el valor almacenado y la sala sustituye cero por uno. No se encontró un mecanismo que actualice espectadores activos. No presentar ese dato como conteo real.
14. **Panel de streaming sin enlaces de paginación.** El controlador pagina de diez en diez, pero la vista no imprime los enlaces; los eventos restantes quedan fuera del acceso normal de esa pantalla.
15. **Verificación de correo inactiva en el modelo.** Hay rutas y middleware `verified`, pero `User` no implementa `MustVerifyEmail`. Determinar si la plataforma requiere correo verificado y probar esa regla explícitamente.
16. **Normalización de roles inconsistente.** `/dashboard` normaliza espacios y mayúsculas, mientras `CheckRole`, `isAdmin()` e `isOperador()` comparan literalmente. Un rol con formato inconsistente puede redirigir a un panel que después devuelve 403.
17. **Cancelados accesibles por URL directa.** El catálogo los excluye, pero la sala y los endpoints de chat no restringen su estado. Definir si cancelar significa ocultar del catálogo o impedir el acceso completo.
18. **Relación uno a uno sin garantía SQL.** `transmisiones.evento_id` tiene índice, pero no restricción única. Dos solicitudes concurrentes podrían crear más de una transmisión por evento.

### Mantenimiento y coherencia

- `Facultad::departamentos()` referencia un modelo `Departamento` inexistente; fallará si se utiliza esa relación.
- Las vistas leen `facultad.siglas`, pero ese atributo no existe en las migraciones revisadas; terminan usando el texto alternativo.
- Los cambios del CRUD redirigen al catálogo público, donde no se imprime el mensaje de éxito que envían.
- Las miniaturas tienen soporte de lectura, pero no un flujo de subida implementado en los formularios/controlador revisados.
- La documentación sobre cuándo aparece el video archivado no coincide totalmente con el código: un evento con URL puede aparecer en anteriores y reproducirse sin estar finalizado.
- `hls.js@latest` se carga desde CDN sin versión fija. Si falla la descarga, la referencia directa a `Hls` puede impedir incluso evaluar el soporte nativo. Faltan recuperación y mensajes claros ante errores de reproducción.
- Copiar enlaces y claves no maneja fallos; verificar específicamente el acceso por IP sobre HTTP descrito en el README.
- React se importa globalmente, pero su punto de montaje está en `welcome.blade.php`, sin una ruta activa encontrada que renderice esa vista. Evaluar si mantenerlo y cargarlo bajo demanda.
- Tailwind 3 está activo mediante PostCSS; `@tailwindcss/vite` 4 está declarado pero no se usa. La configuración de contenido no incluye JSX.
- Hay elementos `main` anidados entre layout y vistas, y contenido muy comprimido en Blade/CSS que dificulta mantenimiento y revisión de accesibilidad.
- Zona horaria configurada en UTC e idioma de ejemplo en inglés; definir visualización local de fechas y mensajes en español.
- El slug al crear usa segundos y puede colisionar para títulos idénticos; al editar cambia de esquema. Las rutas actuales enlazan por ID.
- Git contiene numerosas modificaciones y eliminaciones previas, incluidos residuos de VS Code. No se alteró su staging ni se revirtieron cambios.

## Base positiva

Hay separación entre catálogo y administración, comprobación de roles en servidor, protección de formularios, limitación de intentos de login, regeneración de sesión al autenticar, hashes de contraseña y transacción al crear evento/transmisión. El chat escapa texto al renderizarlo. Las consultas principales utilizan carga anticipada de relaciones y paginación. Compilación y la mayor parte de pruebas existentes funcionan.

## Secuencia propuesta de trabajo

1. Atender dump versionado y políticas de conservación de datos. Corregir pérdida de campos, detalle administrativo, slot de encabezado y preparación de la prueba fallida.
2. Unificar validaciones y formularios; completar datos iniciales, catálogos y pruebas del CRUD con ambos roles.
3. Definir estados de transmisión, autenticación RTMP y configuración HLS; probar OBS → Nginx → navegador en el entorno real.
4. Acotar consultas del chat, manejar errores y completar paginación y navegación.
5. Completar pruebas de catálogo con eventos, sala, chat, regeneración de claves, borrado y restricciones de roles. Revisar migraciones también contra PostgreSQL si será la base de despliegue.
6. Revisar interfaz en móvil/escritorio, accesibilidad, idioma, fechas y dependencias efectivamente utilizadas.

Las pruebas actuales se concentran en autenticación, perfil y redirecciones. Su resultado no verifica todavía el flujo completo de emisión ni el CRUD de eventos con datos reales.


## Actualización de implementación — 19 de septiembre de 2026

Se completó la primera etapa recomendada: gestión de eventos y formularios (puntos 1 y 2 de la propuesta posterior). Los hallazgos anteriores se conservan como diagnóstico histórico.

- Validación centralizada en `EventoRequest`, con permisos, límites de texto, estados permitidos, fechas coherentes y relación carrera/facultad.
- Edición que conserva los campos opcionales omitidos, el slug y la miniatura; permite limpiar explícitamente campos opcionales.
- Formularios compartidos con catálogos por nombre, filtro de carreras, fecha de cierre, subida de miniatura y vista previa.
- Vista de detalle administrativo, encabezado del layout restaurado y navegación administrativa después de guardar/eliminar.
- Miniaturas en disco público, validación de formato/tamaño/dimensiones y limpieza del archivo nuevo ante un fallo de guardado; eliminación de la miniatura sustituida después de guardar.
- Slugs con UUID para evitar colisiones al crear títulos repetidos.
- Seeder principal conectado a los catálogos existentes, sin crear la cuenta de prueba por defecto. No se ha corregido ni certificado el contenido institucional de esos catálogos.
- Prueba de portada reparada mediante `RefreshDatabase` y 12 pruebas adicionales de gestión.

Verificación: suite completa de 43 pruebas y 190 aserciones aprobadas sobre SQLite en memoria; compilación Vite y caché Blade correctas; enlace de almacenamiento público creado. No se hizo inspección de la interfaz en navegador ni emisión OBS real. La integración automática con OBS, moderación de chat, búsqueda del catálogo y cambios de conservación de cuentas pertenecen a etapas posteriores.

Consulta de solo lectura a PostgreSQL: 1 categoría, 0 facultades y 0 carreras. Los selectores están implementados; para utilizarlos con datos reales falta cargar el catálogo académico institucional. No se insertaron nombres de ejemplo en esa base.

## Actualización — catálogo y chat completos

Se implementaron búsqueda por título, filtros combinables por facultad/categoría/estado, conservación de filtros en paginación y sección de próximos eventos. La búsqueda utiliza parámetros SQL y trata `%` y `_` como caracteres literales.

El chat admite moderación por admin/operador con eliminación lógica y registro del moderador, mensajes de error visibles, conservación del borrador y prevención de envíos simultáneos. Se sustituyó la carga inicial ilimitada por un feed de 80 mensajes, caché compartida de 3 segundos y ETag; el cliente consulta cada 5 segundos, pausa pestañas ocultas y aplica espera progresiva ante errores. La lista se renderiza con texto escapado, conserva la posición del lector y refleja retiradas de mensajes.

Validación: 53 pruebas PHP aprobadas (247 aserciones), 4 pruebas JavaScript aprobadas; compilación de assets y vistas correcta. Migración aplicada en PostgreSQL. Comprobación HTTP mediante el kernel de Laravel sobre PostgreSQL: catálogo con búsqueda, sala y chat respondieron 200. No se hizo inspección visual en navegador ni prueba de carga; WebSockets y bloqueo de usuarios no forman parte de esta entrega.

## Catálogo académico cargado — 19 de septiembre de 2026

Se corrigió `FacultadCarreraSeeder` y se ejecutó sobre PostgreSQL: 5 facultades y 24 nombres de carreras. Las modalidades de una misma carrera se agrupan; se cargaron los nombres publicados en las páginas de cada facultad, no una lista de todas las ofertas históricas o extensiones. Se mantienen los registros existentes al repetir el seeder.

Fuentes oficiales consultadas:
- https://ueb.edu.ec/pregrado
- https://ueb.edu.ec/pregrado/facultad-ciencias
- https://www.ueb.edu.ec/pregrado/facultad-de-jurisprudencia-ciencias-sociales-y-politicas
- https://www.ueb.edu.ec/pregrado/facultad-de-ciencias-de-la-educacion-sociales-filosoficas-y-humanisticas
- https://www.ueb.edu.ec/pregrado/facultad-de-ciencias-agropecuarias-recursos-naturales-y-del-ambiente
- https://www.ueb.edu.ec/pregrado/facultad-de-ciencias-de-la-salud-y-del-ser-humano

Verificación del HTML mediante el kernel de Laravel: el formulario de creación devuelve 200 y contiene 5 opciones de facultad y 24 de carrera. Las 12 pruebas de gestión de eventos pasan. Se comprobó también que `chat_messages.deleted_at` existe actualmente y que `/eventos/6` y `/eventos/6/chat` devuelven 200; el error de columna faltante del reporte adjunto no se reproduce en la base actual.


## Diseño del acceso — 20 de septiembre de 2026

Se renovó exclusivamente el login con la paleta azul institucional existente, mascota arriba a la derecha, fondo geométrico discreto, mejor jerarquía tipográfica, tarjeta y campos redondeados, enlaces al catálogo y controles de mostrar contraseña/Bloq Mayús. Estilos aislados en `resources/css/login.css`; formulario y rutas de autenticación conservados. Se contempla navegación por teclado, errores asociados a campos, movimiento reducido y presentación móvil compacta.

Validación: compilación Vite, caché Blade y 4 pruebas de autenticación aprobadas. Inspección de capturas de Firefox a 1440 × 1000 y 390 × 844. La grabación automática solicitada anteriormente sigue pendiente de implementación; en esa tarea solo se inspeccionó la infraestructura y no se activaron servicios de grabación.

### Grabación automática (20 septiembre 2026)

Implementado guardado nativo en Nginx con append para reconexiones, seguimiento autenticado de señal, espera de 90 s, preparación FFmpeg en proceso separado y publicación automática sin aprobación (preferencia confirmada). Enlace manual conservado. Migración aplicada; instalador `scripts/activate-recordings.sh` preparado para root, aún pendiente de ejecución por el usuario. Ver `docs/grabacion-automatica.md`. Conversión real y pruebas de aplicación verificadas; la prueba integral con Nginx aislado no pudo completarse por restricción de red y límite de uso del revisor automático de permisos.
