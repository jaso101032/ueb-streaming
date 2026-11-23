# Plan de pendientes según los requisitos del profesor

Estado revisado el 23 de noviembre de 2026 (la versión anterior era del 18 de
octubre). **Implementado** significa que existe en el código y tiene pruebas
automáticas; **validado** exige una prueba real en la infraestructura y los
dispositivos de destino. No confundir ambos estados.

La guía para ejecutar las validaciones pendientes y reunir las evidencias está
en [cierre-y-validacion.md](cierre-y-validacion.md).

| Prioridad | Requisito | Estado actual | Trabajo pendiente / criterio de aceptación |
| --- | --- | --- | --- |
| P0 | Historial de chat sincronizado con la grabación | Implementado: el historial completo se obtiene en páginas de 200; cada mensaje aparece al llegar a su segundo y desaparece al retroceder. El chat se cierra al finalizar. Hay prueba de backend y Chromium. | Validar en teléfonos reales con la grabación de un directo real (sección V3 de la guía de cierre). |
| P0 | Canal verdaderamente continuo 24/7 | Implementada la prioridad directo → programa → publicidad → espera, la rotación de anuncios activos en los huecos y la agenda de la Parrilla con huecos visibles («Programar aquí»). | Cargar cortinas/publicidad oficial autorizada y probar ciclos completos, reinicios y ausencia de archivos en el servidor de la UEB. Requiere material institucional. |
| P0 | Retraso máximo de cinco segundos y estabilidad | Sin validar: HLS y el reproductor están configurados con búfer corto, pero no existe todavía una medición OBS → espectadores. | Medir con el procedimiento V1 de la guía de cierre (PC, Android y otra red; mediana y percentil 95). No prometer ≤ 5 s hasta superar la prueba. |
| P0 | Compatibilidad y calidad del directo | Parcial y declarado como limitación: OBS envía H.264/AAC y Nginx empaqueta HLS en una sola calidad; no se transcodifica el directo en tiempo real (exige mucha CPU). Las grabaciones y los videos subidos sí se convierten y tienen varias calidades. | Decisión de alcance: documentar la limitación en la tesis o, si el servidor lo permite, probar un perfil de transcodificación en vivo midiendo CPU y retraso. |
| P1 | Varias calidades para videos grabados, programados y del catálogo | **Implementado**: se generan 1080p, 720p, 480p y 360p (solo las que no superan el original) en una lista HLS multicalidad; el reproductor elige automáticamente y permite elegir a mano (⚙ → Calidad). | Validar el cambio de calidad en redes reales (V3). |
| P1 | Publicidad institucional y continuidad después de un vivo | Implementado: formulario separado, conversión, activación/desactivación, rotación en huecos, distintivo «P» y reproducción sin control de avance en `/senal`. | Cargar piezas oficiales y validar la transición en una emisión real. |
| P1 | Cobertura horaria de la parrilla | Implementada: agenda por días con huecos libres, proyección de las próximas 24 horas y aviso de si la publicidad activa puede cubrir los huecos. | Validar zona horaria y cambios de programación en producción. |
| P1 | Política de descargas | Implementado para el equipo: el administrador puede descargar cualquier video (grabaciones, parrilla, catálogo y publicidad, incluso de eventos eliminados) y cada descarga queda en la Auditoría. | Acordar la política institucional para el público. Ningún sitio web puede impedir por completo que se guarde un video que se reproduce; declararlo así. |
| P1 | Identidad oficial y marca de agua | Parcial: recursos institucionales locales y marca UEBTV en el reproductor, configurable. | Auditar cada recurso contra los originales aprobados por la UEB. |
| P1 | Distinguir directo, pregrabado y archivo; contador | Implementado: indicador de directo y espectadores conectados; las grabaciones, programas y videos del catálogo muestran visualizaciones. | Verificarlo con usuarios reales (prueba de usabilidad, V4). |
| P1 | OBS y operación de producción | Guía de 3 pasos dentro de «Emitir con OBS» y scripts locales. | Comprobar en el equipo y servidor definitivos. Transmitir desde fuera de la UEB requiere que TI publique el puerto 1935. |
| P1 | Protocolos, formatos y licencias | Documentado en `arquitectura-multimedia.md`: RTMP/HLS/HTTP sobre TCP; H.264/AAC justificados. | Anexar evidencia de licencias de las compilaciones distribuidas (`ffmpeg -L`, hls.js). |
| P1 | Almacenamiento | **Implementado**: sin tope fijo de tamaño (solo se rechaza sin espacio libre), medidor y desglose en el Resumen, aviso en todo el panel (poco espacio / sin espacio) y «Liberar espacio» con descarga previa. Eliminar borra el evento con todos sus archivos. | Ejecutar respaldo y restauración (V2). |
| P1 | Videos solo para el catálogo | **Implementado**: se suben desde la Biblioteca o «Crear evento», con facultad y carrera opcionales; aparecen cuando están listos y nunca entran al canal. Se agrupan en «Más de…» y se filtran por carrera. | — |
| P1 | Seguridad | **Implementado**: roles y permisos por acción, contraseñas robustas en todos los formularios, límite de intentos, visibilidad por contenido, auditoría inalterable, cabeceras CSP/HSTS/Permissions-Policy, 0 vulnerabilidades conocidas en dependencias. | Revisar en producción que HTTPS y HSTS estén activos (V2). |
| P1 | Accesibilidad | **Implementado y auditado automáticamente**: 0 incumplimientos WCAG 2.1 AA (axe-core) en 22 pantallas y 4 ventanas, claro y oscuro, PC y teléfono; subtítulos automáticos. | Prueba manual con teclado y lector de pantalla (V5). |
| P2 | APK para Android TV / Google TV | Prototipo: WebView a `/senal?modo=tv`, APK de depuración y QR de descarga. | Probar en teléfono y televisor reales; generar APK firmado de distribución. |

## Orden de ejecución

1. Subir el trabajo a `main` y desplegar (aplica la migración nueva).
2. Ejecutar en el servidor de la UEB las validaciones V1 (retraso) y V2 (respaldo y HTTPS).
3. Validar con dispositivos reales (V3) y con usuarios (V4 usabilidad, V5 accesibilidad manual).
4. Cargar el material institucional autorizado y anexar las evidencias a la tesis.

Referencias: [cierre y validación](cierre-y-validacion.md),
[arquitectura multimedia](arquitectura-multimedia.md),
[contenido programado](contenido-programado.md) y
[guía de entrega](ENTREGA_2026-10-12.md).
