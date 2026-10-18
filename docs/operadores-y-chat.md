# Operadores y comentarios

## Permisos

El administrador crea y edita operadores en **Gestionar usuarios**. Puede
asignar, de manera independiente:

| Permiso | Permite |
| --- | --- |
| Gestionar eventos | Crear y editar la ficha y la miniatura de un directo. |
| Emitir con OBS | Ver servidor/clave, regenerar clave y finalizar la emisión. No significa acceso SSH. |
| Moderar chat | Retirar mensajes de un evento. No permite editar cuentas. |
| Controlar comentarios | Activar o desactivar los comentarios de un directo, al crearlo o durante su emisión. |

La biblioteca administrativa, descargas y eliminación de grabaciones, la
parrilla 24/7, la marca de agua y las cuentas siguen siendo exclusivas del
administrador. Los permisos se comprueban en rutas y controladores; ocultar
un botón no constituye autorización. Una cuenta con `operator_permissions`
vacío no obtiene acceso al panel operativo. Las cuentas de operador anteriores
a esta actualización conservan sus permisos históricos hasta que el
administrador las edite; entonces se guarda la selección explícita. Un usuario
ascendido a operador no recibe derechos implícitos. Si se revoca «Emitir con
OBS», conviene regenerar la clave de los eventos en que ese operador haya
configurado OBS, porque una clave ya copiada puede seguir en ese programa.

## Comentarios

Cada directo comienza con comentarios permitidos. El administrador o un
operador con «Controlar comentarios» puede cambiarlo desde el detalle del
evento, el panel de OBS o la propia sala pública. Esta última opción permite
actuar también a un moderador que no tiene acceso a gestión de eventos. El
chat de la sala consulta ese estado cada cinco
segundos y pasa a solo lectura sin perder mensajes. El servidor vuelve a
validar el estado dentro de la transacción de publicación: una petición
manual no elude el cierre. Al finalizar el directo el chat queda cerrado y
permanece el historial vinculado a los minutos de la grabación.

El administrador puede marcar «Restringir comentarios» en una cuenta. Esa
persona seguirá viendo la conversación y los videos, pero el servidor
rechazará nuevos mensajes hasta que se quite la restricción. La moderación de
mensajes y esta restricción de cuenta son funciones distintas: un operador
moderador no puede modificar usuarios.

## Actualización

Después de desplegar el código, ejecutar `php artisan migrate --force` y
recompilar los recursos con `npm run build` según el proceso del servidor. La
migración agrega `users.operator_permissions` (JSON nullable),
`users.chat_restricted` (booleano) y `eventos.chat_enabled` (booleano).
