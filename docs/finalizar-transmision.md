# Finalizar una transmisión

Admin y operador disponen de «Finalizar transmisión» en Control de streaming y en el detalle del evento. La acción pide confirmación, desconecta el publicador de ese evento mediante el control local de Nginx y guarda `finalizado_en`, `activo=false`, espectadores cero y evento finalizado. No cambia la fecha de cierre programada ni inventa un video grabado. Repetir la solicitud no altera la hora del cierre.

Si Nginx no confirma el corte, se muestra un error y no se finaliza el evento en la base de datos. Las llamadas externas no son transacciones: si el corte ocurre pero se pierde la respuesta, puede repetirse la operación. Nginx devuelve cero cuando ya no hay publicadores conectados.

## Activación en este equipo

Ejecutar desde la raíz del proyecto:

```bash
bash scripts/activate-stream-control.sh
```

Solicitará la contraseña de sudo en tu terminal. Hace copia de seguridad de nginx.conf, instala controles, valida con nginx -t y recarga; restaura la configuración si falla. Está diseñado para el nginx.conf local inspeccionado; no reemplaza toda la configuración. Requiere Laravel atendiendo en http://127.0.0.1:8000. Al pasar a producción, ajustar esa dirección al servidor permanente de la aplicación. Mantener ese servicio disponible: el modo estricto corta conexiones si no puede consultar la autorización.

El token privado está en STREAMING_CALLBACK_TOKEN (.env), debe ser aleatorio de 64 caracteres hexadecimales y coincidir con Nginx. No compartirlo. Si se utiliza caché de configuración, actualizarla antes de ejecutar el script. El endpoint de control escucha exclusivamente en 127.0.0.1:8091. Los callbacks POST están exentos de CSRF exclusivamente en su ruta y autenticados mediante el token.

`on_publish` rechaza claves desconocidas y eventos finalizados/cancelados. `on_update` aplica la misma comprobación cada tres segundos para impedir que una reconexión simultánea al cierre continúe. La clave de un evento finalizado no se puede regenerar para reabrirlo. El botón no apaga OBS: detiene su publicación en este servidor. Puede quedar un breve tramo de video en el búfer del espectador.

La grabación automática y la publicación de MP4 están implementadas; requieren activar previamente `scripts/activate-recordings.sh`. Ver `docs/grabacion-automatica.md`. El botón de cierre no recupera contenido anterior a la activación; el enlace manual continúa como alternativa.

Documentación oficial: https://github.com/arut/nginx-rtmp-module/wiki/Directives (rtmp_control, on_publish, on_update).
