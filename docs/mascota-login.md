# Mascota del login

Asset: `public/images/mascota-ueb-login.png`.
Preparado con la herramienta integrada image_gen a partir de la captura facilitada por el usuario; es una adaptación generada, no el archivo gráfico institucional original.

Prompt inicial: aislar el zorro de la referencia, conservando gafas negras, gorra UEB, chaqueta roja/azul/blanca, pantalón azul, zapatos blancos, cola y postura con piernas cruzadas. Eliminar interfaz de navegador, WhatsApp y letras del banner. Cuerpo completo y fondo transparente.

El primer resultado contenía un fondo cuadriculado, por lo que se descartó para el sitio.

Prompt final:

> Edit the generated fox mascot asset. Keep the entire fox, clothing, UEB marks, pose, tail, shoes and proportions unchanged. Replace ALL gray checkerboard background with perfectly uniform solid dark navy #071a35, RGB 7,26,53. No checkerboard, no transparency simulation, no texture or gradient in background. Full-body portrait, small padding around entire character. Intended for dark navy login page. Preserve mascot identity.

Integración: panel de bienvenida de `resources/views/auth/login.blade.php`, en la esquina superior derecha del panel azul, con posición absoluta y tamaño compacto en móvil. El texto central conserva su distribución original.

Validación: compilación Vite y cuatro pruebas de autenticación correctas. No se realizó inspección visual en navegador.
