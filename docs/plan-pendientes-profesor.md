# Plan de pendientes según los requisitos del profesor

Estado revisado el 12 de octubre de 2026. **Implementado** significa que existe
en el código; **validado** exige una prueba real en la infraestructura y los
dispositivos de destino. No confundir ambos estados.

| Prioridad | Requisito | Estado actual | Trabajo pendiente / criterio de aceptación |
| --- | --- | --- | --- |
| P0 | Historial de chat sincronizado con la grabación | Parcial: se guarda el segundo de cada mensaje, el chat se cierra al finalizar y se puede saltar a ese minuto. El panel muestra los últimos 80 mensajes de inmediato. | Hacer que los mensajes aparezcan al llegar a su segundo de reproducción, incluso al adelantar o retroceder; conservar paginación/historial y no permitir mensajes nuevos tras el directo. Probar en PC y móvil. |
| P0 | Canal verdaderamente continuo 24/7 | Implementada la prioridad directo → programa → publicidad → espera y la rotación de anuncios activos en huecos. Sin piezas autorizadas cargadas, todavía aparece la pantalla de espera. | Conseguir y cargar cortinas/publicidad oficial autorizada; probar ciclos reales completos, reinicios, redes y ausencia de archivos. Requiere material institucional. |
| P0 | Retraso máximo de cinco segundos y estabilidad | Sin validar: HLS/reproductor están configurados con búfer corto, pero no existe una medición OBS → espectadores. | Medir en PC, Android y otra red con reloj visible; registrar mediana y percentil 95, cortes, recuperación y audio. Ajustar segmentos, lista HLS y búfer según los datos; no prometer ≤5 s hasta superar la prueba. |
| P0 | Compatibilidad y calidad del directo | Parcial: OBS debe enviar H.264/AAC; Nginx empaqueta HLS, pero no transcodifica video/audio del directo ni ofrece calidades adaptativas. Grabaciones y videos subidos sí se convierten a MP4 H.264/AAC. | Diseñar y probar transcodificación en vivo a perfiles HLS compatibles (incluyendo audio), con adaptación a distintas redes y control de CPU. Comparar calidad, consumo y retraso antes de adoptar un perfil. |
| P1 | Varias calidades para videos grabados y programados | No implementado: cada video procesado tiene un solo MP4 optimizado, hasta 1080p. | Generar representaciones de menor resolución/bitrate solo cuando la fuente lo permita; publicar una lista HLS multicalidad y ofrecer selección automática y manual en el reproductor. Probar cambio de calidad sin perder el punto de reproducción. |
| P1 | Publicidad institucional y continuidad después de un vivo | Implementado el formulario separado para el administrador, conversión, activación/desactivación, rotación en huecos, distintivo «P» y reproducción sin control de avance en `/senal`. | Cargar piezas oficiales/autorizadas y validar la transición en una emisión real, incluidos cortes de OBS y cambio de anuncio. La frecuencia editorial de cada campaña puede ajustarse después. |
| P1 | Cobertura horaria de la parrilla | Parcial: el panel suma la duración de videos disponibles y el tiempo restante, pero no garantiza cobertura sin huecos. | Mostrar hora real estimada de cobertura y próximos intervalos vacíos, considerando directos, anuncios y zona horaria. Alertar al administrador antes de quedarse sin contenido. |
| P1 | Política de descargas | Parcial: existe ruta de descarga explícita solo para administrador. La ruta pública de reproducción de MP4 permite técnicamente guardar el archivo desde el navegador. | Acordar la política institucional: quién puede descargar, quién puede reproducir y si se aceptan las limitaciones de protección web. No afirmar que la descarga está impedida por completo solo por ocultar el botón. |
| P1 | Identidad oficial y marca de agua | Parcial: hay recursos institucionales locales y marca UEBTV en la esquina superior derecha del reproductor; revisar coherencia de origen y tamaños en todas las vistas. | Auditar cada recurso contra los originales aprobados por la UEB, sustituir cualquier imagen no autorizada y probar legibilidad en claro/oscuro, PC y móvil. Las animaciones JS son mejora opcional, sin afectar rendimiento. |
| P1 | Distinguir directo, pregrabado y archivo; contador | Implementado en la interfaz: el directo tiene indicador y espectadores conectados fuera del visor; las grabaciones/videos programados usan visualizaciones. | Verificarlo con usuarios reales y en despliegue. Aplicar las migraciones recientes antes de evaluar las visualizaciones. |
| P1 | OBS y operación de producción | Hay guía de configuración y scripts locales, no una certificación del entorno final. | Comprobar versión soportada de OBS, códecs, audio, red, reconexión, grabación y workers en el equipo/servidor definitivo; dejar resultados reproducibles. |
| P1 | Protocolos, formatos y licencias | Documentado en `arquitectura-multimedia.md`: RTMP/HLS/HTTP usan TCP; la plataforma no usa UDP. Se justifica H.264/AAC y se identifican licencias. | Revisar avisos de licencia de las compilaciones y dependencias realmente distribuidas (`ffmpeg -L`, hls.js) y anexar evidencia del despliegue. |
| P2 | APK para Android TV / Google TV | No implementado; requisito opcional. | Investigar experiencia de mando a distancia, reproducción HLS, instalación y mantenimiento; prototipo solo después de estabilizar la web y la señal. |

## Orden de ejecución

1. Completar el historial sincronizado del chat y sus pruebas automatizadas.
2. Conseguir y cargar cortinas/anuncios oficiales autorizados; validar la rotación ya implementada y añadir la cobertura horaria.
3. Medir un directo real antes de cambiar el pipeline; definir perfiles de transcodificación en vivo según calidad, recursos y latencia observados. Después, añadir calidades adaptativas a los videos bajo demanda reutilizando el mismo reproductor.
4. Auditar permisos de descarga, identidad y despliegue; cerrar documentación y evidencias.
5. Evaluar el APK únicamente como extensión opcional.

Referencias: [arquitectura multimedia](arquitectura-multimedia.md),
[contenido programado](contenido-programado.md) y
[guía de entrega](ENTREGA_2026-10-12.md).
