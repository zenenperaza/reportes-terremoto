# Notificaciones con permisos y equipos

El permiso protegido **Recibir notificaciones** se asigna inicialmente a los roles `admin`, `coordinator` y `reporter`. Puede retirarse o asignarse desde la edición de Roles. La migración agrega el permiso sin sustituir los demás. Repetir el comando de migración no vuelve a asignar permisos retirados: Laravel omite migraciones ya ejecutadas. No otorga permisos para ver, editar o eliminar registros, ni administrar el mantenimiento.

## Destinatarios

- Administradores activos con el permiso: reciben el nombre del usuario que inició sesión correctamente. No se notifica un intento fallido ni el primer paso de doble autenticación: solo el acceso confirmado. No se notifican el acceso propio, las consultas ni los heartbeats. Aquí también cuentan como administradores los roles personalizados con `administrar sistema`.
- Registradores y coordinadores activos con el permiso: reciben avisos de registros/beneficiarios agregados, actualizados o eliminados por **otro** integrante de un equipo activo compartido. Deben además tener acceso al propietario del registro según las reglas actuales del SIA. No se notifica a los administradores sobre cambios de equipos en esta versión; sus avisos remotos son los accesos.
- Estar en varios equipos no duplica un aviso. La pertenencia no es transitiva: compartir equipo con alguien que pertenece a otro equipo no habilita avisos de ese segundo equipo. Los grupos inactivos no participan. El permiso del destinatario se comprueba al generar y consultar; también se comprueban nuevamente los equipos y la visibilidad antes de devolver el contenido.
- Las confirmaciones de acciones propias y los avisos de mantenimiento de escritorio de la versión anterior también respetan el nuevo permiso. El banner de mantenimiento dentro de SIA y el número de usuarios conectados continúan disponibles para todos.

Los avisos remotos incluyen solo tipo, identificador, fecha y un mensaje con el nombre del integrante que realizó la acción. No llevan nombres, documentos, teléfonos ni información de beneficiarios, IP, correo, evidencias o enlaces de edición. Los avisos no cambian ningún permiso de los registros.

## Entrega

La consulta autenticada `/sistema/estado` actualiza el conteo y obtiene los avisos cada 15 segundos, sin consultas adicionales de lectura ni bitácora/respaldos por heartbeat. La campana muestra hasta 20 avisos pendientes de las últimas 24 horas y su cantidad total. Cada usuario puede marcarlos como vistos incluso sin permiso de notificaciones de Windows. La bandeja y los reconocimientos siempre están restringidos al usuario de la sesión; no se acepta un destinatario enviado por el navegador.

Para escritorio hay que pulsar Activar y permitir las notificaciones del navegador. Se muestran como máximo tres avisos remotos nuevos por consulta; los restantes continúan pendientes. El navegador reconoce al servidor los identificadores que aceptó mostrar. Si falla el reconocimiento, se reintenta sin volver a mostrar esos avisos mientras sus identificadores sigan en el historial local de deduplicación. El permiso del navegador no garantiza que Windows muestre el aviso si está en No molestar. Los avisos pendientes no son un historial de auditoría: la bitácora sigue siendo el registro de acciones.

SIA debe permanecer abierto, aunque esté minimizado. No se ha implementado Web Push para SIA cerrado. Las pestañas suspendidas, sesiones vencidas y falta de conexión pueden retrasar la entrega. Los avisos persistidos duran más que una pestaña: otro dispositivo del destinatario puede recibir los que aún estén pendientes, dentro de las 24 horas. No se reenvían a nuevos integrantes avisos generados cuando no pertenecían al equipo o no tenían permiso.

### Probar notificación

La campana muestra un resultado independiente al pulsar **Probar notificación**, que no se borra con las consultas automáticas. Cada prueba usa un identificador nuevo para no sustituir silenciosamente una prueba anterior. Se solicita mantener el aviso visible hasta descartarlo cuando el navegador lo admite.

Si hay un service worker activo, la prueba utiliza `showNotification`; si no está disponible o rechaza el envío, intenta la API de escritorio. Se capturan también errores asíncronos y se limita la espera. El resultado distingue entre envío aceptado, confirmación del navegador, falta de confirmación y fallo: ninguno garantiza que Windows haya mostrado un banner. Revisar el centro de notificaciones, No molestar y la configuración de notificaciones del navegador si no aparece. No se solicitan permisos al pulsar Probar.

Al pulsar una notificación de la PWA se enfoca una pestaña existente de SIA, sin recargar ni navegar sobre formularios con cambios sin guardar. Si ya no hay una pestaña de SIA, se abre la raíz del sistema. Esta prueba no habilita Web Push ni entrega de nuevos avisos con SIA cerrado.

Las llamadas de generación se realizan tras las operaciones correctas del controlador, no en cada actualización automática de totales ni en la bitácora. Una transacción envolvente que se revierte también revierte sus avisos. Un fallo al generar el aviso no impide guardar datos ni iniciar sesión. Si faltan las migraciones, no hay bandeja; el resto del sistema y el banner continúan funcionando.

## Despliegue

Para subir conjuntamente todos los pendientes del 13/10/2026, usar `only=actualizacion-sia`: incluye también la migración de presencia, el menú y la corrección de la prueba de notificaciones. Ver [despliegue-sia-2026-10-13.md](despliegue-sia-2026-10-13.md). No es necesario ejecutar además los modos individuales.

Respaldar antes de subir. No subir la base local. Incluir los archivos de mantenimiento en vivo y notificaciones de escritorio documentados en `mantenimiento-en-vivo.md`, más:

- `database/migrations/2026_10_09_120000_create_system_notifications_table.php`
- `database/migrations/2026_10_09_120100_add_receive_notifications_permission.php`
- `app/Models/SystemNotification.php`
- `app/Support/SystemNotifications.php`
- Las versiones actualizadas de `app/Models/User.php`, `app/Http/Controllers/AuthController.php`, `ReportController.php`, `SystemMaintenanceController.php`, `PermissionController.php` y `TemporaryMaintenanceController.php`.
- `routes/web.php`, `resources/views/layouts/partials/desktop-notifications.blade.php` y `public/js/desktop-notifications.js`.
- `public/service-worker.js` y `public/js/pwa.js` (versión 46 para las pruebas persistentes).

Con consola, aplicar solo las dos migraciones nuevas y limpiar cachés:

```text
php artisan migrate --path=database/migrations/2026_10_09_120000_create_system_notifications_table.php --force
php artisan migrate --path=database/migrations/2026_10_09_120100_add_receive_notifications_permission.php --force
php artisan optimize:clear
```

Sin consola se preparó el modo específico, con el token privado habitual:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=notificaciones&token=TU_TOKEN
```

Este modo comprueba los archivos, limpia cachés, aplica únicamente las dos migraciones y reconstruye cachés. No ejecuta seeders, importaciones, cambios en registros/beneficiarios/equipos ni bloqueos. Si todavía no está desplegada la presencia de usuarios, aplicar también el modo `only=mantenimiento-en-vivo` documentado previamente. Nunca ejecutar el modo general sin `only` para este cambio. No compartir el token. Recargar las páginas abiertas una vez para cargar el JavaScript nuevo.

## Verificación

Pruebas SQLite en memoria, sin tocar la base real:

```text
php artisan test --compact tests/Feature/TeamNotificationsTest.php tests/Feature/TemporaryMaintenanceNotificationsTest.php
node --test tests/JavaScript/desktop-notifications.test.cjs tests/JavaScript/service-worker-notifications.test.cjs
```

Probar con sesiones separadas: administrador, dos integrantes de un equipo y un integrante ajeno. Habilitar notificaciones de escritorio y probar desde la campana. Confirmar que un acceso válido llega solo al administrador y que guardar/editar/eliminar por un integrante llega solo a sus compañeros autorizados. Quitar el permiso o la pertenencia al equipo y comprobar que dejan de recibirse avisos. No hacer operaciones de prueba sobre registros reales en producción.
