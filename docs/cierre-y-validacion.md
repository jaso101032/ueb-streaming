# UEBTV · Cierre del proyecto y guía de validación

Estado al 23 de noviembre de 2026. Este documento resume la calidad del
sistema (seguridad, accesibilidad y usabilidad), las evidencias automáticas que
ya existen y los pasos para completar las validaciones que solo pueden hacerse
con el servidor de la UEB, equipos reales y personas.

> **Implementado** = existe en el código y tiene pruebas automáticas.
> **Validado** = se comprobó en el entorno real y hay evidencia anexable.

---

## 1. Resumen de calidad

### 1.1 Seguridad de la información (tríada CID)

| Pilar | Controles implementados | Evidencia |
| --- | --- | --- |
| **Confidencialidad** | Roles (administrador, operador con permisos por acción, comunidad); contraseñas de ≥ 8 caracteres con mayúsculas, minúsculas, números y símbolos en registro, cambio, recuperación y alta de usuarios; límite de intentos de inicio de sesión; visibilidad por contenido (público, comunidad UEB, solo el equipo) — lo restringido no revela que existe; la clave de OBS nunca se muestra al público; cookies seguras y `APP_DEBUG=false` en producción. | `tests/Feature/AccessControlsTest.php`, `OperatorPermissionsAndChatControlsTest.php`, `CatalogVideoTest.php`, `Auth/*` |
| **Integridad** | Auditoría que no se puede editar (quién, qué, cuándo, IP); huellas SHA-256 del original y del MP4; validación de todos los archivos subidos; borrado en orden seguro (primero la base de datos, de forma atómica, y después los archivos); protección CSRF en todos los formularios. | `StorageCleanupTest.php`, `AutomaticRecordingTest.php`, Auditoría del panel |
| **Disponibilidad** | Servicios con reinicio automático y chequeos de salud; canal 24/7 con prioridad directo → programa → publicidad → espera; monitor de espacio con avisos y «Liberar espacio»; reserva de 2 GB para que una grabación en vivo nunca se quede sin disco; trabajos (conversión, calidades, subtítulos) que se recuperan tras una interrupción; guion de respaldo y restauración. | `docker-compose.production.yml`, `StorageUsageTest.php`, `ChannelTest.php`, `scripts/piloto/respaldo.sh` |

**Cabeceras de seguridad** (todas las páginas): Content-Security-Policy estricta
(solo recursos del propio sitio; sin scripts externos), Strict-Transport-Security
en HTTPS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
Permissions-Policy (cámara, micrófono y ubicación desactivados) y
Cross-Origin-Opener-Policy. Pruebas: `tests/Feature/SecurityHeadersTest.php` y
`tests/Browser/security-headers.spec.js` (22 pantallas sin ningún bloqueo).

**Dependencias:** `composer audit` y `npm audit` → **0 vulnerabilidades conocidas**
(revisado el 23/11/2026).

### 1.2 Accesibilidad (WCAG 2.1 nivel AA)

- **Auditoría automática con axe-core: 0 incumplimientos** en 22 pantallas y
  4 ventanas abiertas (Subir video, Liberar espacio, Programar video y la
  confirmación «¿Estás seguro?»), en modo claro y oscuro, en computadora y en
  teléfono. Prueba permanente: `tests/Browser/accessibility.spec.js`.
- Correcciones hechas durante la auditoría: contraste de los textos
  secundarios (de 4,49:1 a 5,06:1), color de los nombres del chat (todos los
  tonos ≥ 4,5:1), textos de Marca de agua en modo oscuro y el gráfico de
  estadísticas, que ahora es navegable con lector de pantalla.
- Enlace «Saltar al contenido», foco visible, ventanas que se cierran con Esc,
  avisos anunciados (`aria-live`), botones de ícono con nombre accesible,
  imágenes con texto alternativo, estados que no dependen solo del color y
  respeto a «reducir movimiento».
- **Subtítulos automáticos** (Whisper) en grabaciones y videos subidos
  (criterio WCAG 1.2.2).

La auditoría automática detecta cerca de un tercio a la mitad de los problemas
posibles; por eso se completa con la prueba manual V5.

### 1.3 Usabilidad

Principios aplicados en todo el panel: un solo botón principal por pantalla,
íconos con texto al pasar el mouse, rojo para borrar y confirmación «¿Estás
seguro?», formularios en ventana centrada, mensajes de resultado («Cambios
guardados», «Se liberaron 8 MB»), barras de progreso, estados vacíos que
explican qué hacer, prevención de errores (espacio, directo en curso) y
lenguaje en español sin términos técnicos. Diseño adaptable verificado en 360,
390, 768 y 1440 px.

**Falta medirla con personas** (V4, cuestionario SUS).

### 1.4 Pruebas automáticas

| Conjunto | Resultado | Cómo ejecutarlo |
| --- | --- | --- |
| Servidor (PHPUnit) | 212 pruebas, 1708 comprobaciones, todas correctas | `php artisan test` |
| Navegador (Playwright, Chromium) | 63 pruebas, todas correctas | `npx playwright test` |
| Dependencias PHP | 0 vulnerabilidades | `composer audit` |
| Dependencias JS | 0 vulnerabilidades | `npm audit` |

---

## 2. Validaciones pendientes (entorno real)

Anotar cada resultado en la tabla correspondiente y guardar capturas, videos o
archivos de registro como anexo.

### V1 · Retraso del directo (requisito del profesor: ≤ 5 s)

1. En OBS, agregar una fuente de texto o navegador con un **reloj con
   milisegundos** (por ejemplo, la hora del sistema) visible en la imagen.
2. Transmitir a la plataforma y abrir la señal en: PC en la red de la UEB,
   teléfono Android con datos móviles y un equipo en otra red.
3. Tomar una **foto que muestre a la vez** el reloj original (pantalla de OBS)
   y el reloj en el dispositivo. Retraso = diferencia entre ambos.
4. Diez mediciones por dispositivo, espaciadas un minuto. Anotar también
   cortes, recuperación y calidad del audio.

| Dispositivo / red | Mediana (s) | Percentil 95 (s) | Cortes | Cumple ≤ 5 s |
| --- | --- | --- | --- | --- |
| PC · red UEB | | | | |
| Android · datos móviles | | | | |
| Equipo · otra red | | | | |

Si no se cumple, ajustar la duración de segmento y la lista HLS (ver
`docs/arquitectura-multimedia.md`) y repetir.

### V2 · Respaldo, restauración y HTTPS (CP-NF-12)

1. Ejecutar tres ciclos de `scripts/piloto/respaldo.sh` (instrucciones en
   `scripts/piloto/README.md`). Criterio: RPO ≤ 24 h, RTO ≤ 4 h, recuentos y
   huellas coincidentes. Evidencia: `respaldo_cicloN/registro.txt`.
2. Comprobar las cabeceras en producción:
   `curl -sI https://<dominio>/login` debe mostrar `strict-transport-security`
   y `content-security-policy`.
3. Programar la copia automática diaria en el servidor (cron o Dokploy).

| Ciclo | RTO medido | Recuentos iguales | Huellas iguales | MP4 restaurado reproduce |
| --- | --- | --- | --- | --- |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

La prueba de carga CP-NF-05 (`scripts/piloto/carga.py`) se ejecuta en el mismo
entorno.

### V3 · Dispositivos reales

| Prueba | Dispositivo | Resultado | Observaciones |
| --- | --- | --- | --- |
| El chat de una grabación aparece en su segundo y desaparece al retroceder | Teléfono Android | | |
| Cambio de calidad (⚙ → Calidad) sin perder el punto de reproducción | Teléfono con datos | | |
| Subtítulos (CC) visibles y sincronizados | PC y teléfono | | |
| Canal 24/7: paso de programa a publicidad y a directo | PC | | |
| APK en televisor: navegación con el control remoto | Android TV / Google TV | | |
| Subir un video de más de 1 GB desde la red de la UEB | PC | | |

### V4 · Prueba de usabilidad (cuestionario SUS)

**Participantes:** 5 a 8 personas (al menos 2 administradores u operadores y
3 estudiantes o docentes). No se les ayuda durante las tareas; se anota si la
completan, el tiempo y dónde dudan.

**Tareas para el equipo (administrador / operador):**

1. Crear un evento en vivo y encontrar los datos para configurar OBS.
2. Subir un video al catálogo de una carrera, con portada.
3. Programar un video en la Parrilla 24/7 en el primer hueco libre.
4. Cambiar el título de un video ya subido.
5. Liberar espacio eliminando un video antiguo, después de descargarlo.

**Tareas para la comunidad (estudiante / docente):**

1. Encontrar en el catálogo un video de tu carrera.
2. Activar los subtítulos y cambiar la calidad del video.
3. Ver qué se está emitiendo ahora en la señal 24/7 y qué viene después.
4. Dejar un comentario en un directo.

| Participante | Rol | Tareas completadas | Tiempo total | Puntaje SUS |
| --- | --- | --- | --- | --- |
| P1 | | / | | |
| P2 | | / | | |
| P3 | | / | | |
| P4 | | / | | |
| P5 | | / | | |

**Cuestionario SUS** (1 = totalmente en desacuerdo … 5 = totalmente de acuerdo):

1. Creo que me gustaría usar esta plataforma con frecuencia.
2. Encontré la plataforma innecesariamente compleja.
3. Pensé que la plataforma era fácil de usar.
4. Creo que necesitaría el apoyo de un técnico para poder usar esta plataforma.
5. Las distintas funciones de la plataforma estaban bien integradas.
6. Pensé que había demasiada inconsistencia en la plataforma.
7. Imagino que la mayoría de las personas aprendería a usar esta plataforma muy rápidamente.
8. Encontré la plataforma muy incómoda de usar.
9. Me sentí muy seguro/a usando la plataforma.
10. Necesité aprender muchas cosas antes de poder usar la plataforma.

**Cálculo:** para las preguntas impares, restar 1 a la respuesta; para las
pares, restar la respuesta a 5. Sumar los diez valores y multiplicar por 2,5
(resultado de 0 a 100). Interpretación habitual: ≥ 68 por encima del promedio,
≥ 80 muy buena. Reportar la media y el rango de los participantes.

### V5 · Accesibilidad manual (10 minutos por pantalla)

Pantallas: Catálogo, Ver video, Login, Resumen, Subir video.

**Solo teclado** (sin mouse):

- [ ] Con Tab se llega a todos los botones, enlaces y campos, en un orden lógico.
- [ ] Siempre se ve dónde está el foco.
- [ ] «Saltar al contenido» aparece con el primer Tab y funciona.
- [ ] Las ventanas se abren, se usan y se cierran con Esc; el foco vuelve al botón que las abrió.
- [ ] El reproductor se controla con teclado (reproducir/pausar, volumen, pantalla completa, subtítulos).

**Lector de pantalla** (NVDA en Windows o TalkBack en Android):

- [ ] El título de cada página y los encabezados se anuncian correctamente.
- [ ] Los botones de ícono dicen qué hacen («Eliminar evento…», «Descargar…»).
- [ ] Los mensajes («Cambios guardados», errores del formulario) se anuncian solos.
- [ ] Los campos del formulario se anuncian con su etiqueta.
- [ ] El estado de un video («En vivo», «Preparando video») se anuncia en texto.

**Visual:**

- [ ] La página se usa con zoom al 200 % sin perder contenido.
- [ ] Los subtítulos se activan y son legibles.

---

## 3. Limitaciones conocidas (declarar en la tesis)

1. **El directo se emite en una sola calidad.** Generar varias calidades en
   tiempo real requiere transcodificación con mucha CPU; las grabaciones y los
   videos subidos sí tienen 1080p, 720p, 480p y 360p.
2. **Transmitir desde fuera de la UEB** depende de que TI publique el puerto
   RTMP 1935 (o de una VPN).
3. **Ningún sitio web puede impedir del todo la descarga** de un video que se
   reproduce; la plataforma limita la descarga directa al equipo y la registra.
4. **El retraso del directo** depende de la red y del búfer HLS; el objetivo de
   5 s está configurado, pero solo vale tras la medición V1.
5. **La APK** es un prototipo (WebView) en versión de depuración.

## 4. Lista de cierre

- [ ] Subir el trabajo a `main` y desplegar (la migración se aplica sola).
- [ ] V1 Retraso · V2 Respaldo y HTTPS · V3 Dispositivos.
- [ ] V4 Usabilidad (SUS) · V5 Accesibilidad manual.
- [ ] Cargar el material institucional autorizado (cortinas y publicidad).
- [ ] Anexar a la tesis: este documento con las tablas llenas, los registros de
      `scripts/piloto`, los informes de pruebas y las capturas.
