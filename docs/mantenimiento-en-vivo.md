# Mantenimiento con aviso en vivo

En Configuración → Configuraciones → Mantenimiento se conserva el bloqueo inmediato y se agregan:

- Número de usuarios conectados: usuarios activos distintos con un heartbeat de una sesión autenticada en los últimos 120 segundos. Varias pestañas o dispositivos del mismo usuario cuentan una sola vez. Es un conteo de presencia reciente, no una conexión permanente exacta. Al cerrar el navegador o perder la red desaparecen después de 2 minutos; cerrar sesión elimina inmediatamente esa sesión, sin eliminar las de otros dispositivos. Incluye administradores. Todos los usuarios autenticados activos pueden ver el total, sin nombres ni detalles de las sesiones.
- El footer de todas las páginas autenticadas muestra la etiqueta «Usuarios conectados» y se actualiza en la misma consulta, sin peticiones adicionales. En Mantenimiento se actualizan simultáneamente la tarjeta y el footer. La administración del bloqueo sigue disponible solo para administradores.
- Avisar y programar bloqueo en 5 o 10 minutos, con mensaje personalizable de hasta 300 caracteres. Permite reemplazar una programación pendiente o cancelarla. No permite reprogramar un bloqueo ya activo: primero debe habilitarse el sistema.
- Aviso persistente en todas las páginas autenticadas, con cuenta regresiva. Se consulta el servidor cada 15 segundos y al recuperar foco o conexión. El mensaje se inserta como texto, no HTML. El plazo usa el reloj del servidor, no el de la PC. Pestañas suspendidas, falta de red o sesiones expiradas pueden demorar el aviso.
- El servidor aplica el bloqueo desde la hora guardada, sin cron, workers ni WebSockets. El aviso no recarga ni cierra formularios, no guarda automáticamente y no garantiza recuperar cambios no guardados. Al vencimiento, las nuevas operaciones de no administradores reciben 503; administradores y cierre de sesión conservan acceso. Una operación ya iniciada antes del bloqueo puede terminar. La pantalla abierta se conserva para poder copiar información no guardada. Al cancelar o habilitar, el aviso desaparece en la siguiente consulta.

Los usuarios deben abrir/recargar una página una vez después del primer despliegue para cargar el script nuevo. Después los avisos llegan sin recargar. El heartbeat es POST con sesión y CSRF, no recibe el usuario desde el cliente, no se registra cada 15 segundos en la bitácora y no ejecuta respaldos automáticos. Las acciones administrativas de bloqueo sí mantienen la bitácora normal. No se registran IP, nombres ni datos de beneficiarios en la tabla de presencia; solo hash de sesión, ID de usuario y última respuesta. Se purgan presencias con más de un día.

## Despliegue

Para subir conjuntamente todos los pendientes del 13/10/2026, usar `only=actualizacion-sia`: incluye esta migración de presencia y las dos de notificaciones en una sola ejecución. Ver la lista completa de archivos y las precauciones en [despliegue-sia-2026-10-13.md](despliegue-sia-2026-10-13.md). Los modos individuales descritos abajo siguen disponibles.

### Notificaciones de escritorio

La campana del encabezado está disponible para todos los usuarios autenticados. Desde allí cada usuario puede activar, desactivar y probar las notificaciones en su navegador. El permiso se solicita solo al pulsar Activar; si fue bloqueado, debe habilitarse manualmente en la configuración del navegador. Se necesita un navegador de escritorio compatible y HTTPS; localhost permite las pruebas locales. Revisar también los permisos de notificaciones y el modo No molestar del sistema operativo.

Actualización: la activación de escritorio ahora requiere además el permiso del SIA **Recibir notificaciones**. La campana incorpora una bandeja para accesos (administradores) y cambios de otros integrantes de equipos compartidos (registradores/coordinadores), con controles de autorización en el servidor. Ver `notificaciones-por-equipos.md` para las dos migraciones nuevas y el modo de despliegue `only=notificaciones`. El banner de mantenimiento dentro de SIA no depende de ese permiso.

Los avisos notifican la programación o reprogramación del mantenimiento, su inicio y la cancelación/finalización detectada mientras la página está abierta. No notifican cada segundo ni cada consulta. La preferencia y el último identificador de aviso se guardan por usuario en el navegador, sin datos de beneficiarios ni texto de mensajes. Si el almacenamiento está restringido, la deduplicación solo dura en esa página. Al pulsar una notificación se enfoca la página abierta sin navegar ni recargar formularios.

También se notifica al abrir SIA (una vez por pestaña) y después de un inicio de sesión confirmado, incluida la verificación de dos factores. Los mensajes de éxito o error que los controladores muestran tras una redirección generan avisos de guardado, actualización, eliminación, operación completada o revisión. La carga inicial de filtros, la navegación y las consultas de presencia no generan avisos de operaciones. Los avisos contienen mensajes fijos, sin nombres, cédulas, teléfonos ni texto del formulario. Los errores solo indican que debe revisarse SIA, sin afirmar que una operación de resultado incierto no se haya guardado.

Los formularios AJAX de registros/beneficiarios notifican después de recibir la confirmación del servidor, incluidos el guardado, la edición y la eliminación desde la ficha o el formulario. Las exportaciones preparadas por `report-export.js` también notifican su resultado. La API `window.SiaDesktopNotifications.notify(kind)` permite integrar otras acciones; solo admite los tipos predefinidos `opened`, `saved`, `updated`, `deleted`, `success`, `error` y `exported`, no mensajes arbitrarios. Los últimos 30 identificadores se guardan para evitar repetir eventos renderizados en una página, sin guardar su contenido. El aviso de apertura usa almacenamiento de sesión por pestaña; si está restringido, la protección entre navegaciones no está garantizada.

Esta versión usa las consultas en vivo existentes: SIA debe permanecer abierto, aunque esté minimizado. No implementa Web Push, no recibe nuevos avisos con todas las pestañas cerradas y no garantiza entrega si el navegador suspende pestañas, se pierde la conexión o la sesión expira. Los avisos dentro de SIA continúan funcionando aunque se deniegue el permiso de escritorio.

Subir también `resources/views/layouts/partials/desktop-notifications.blade.php` y `public/js/desktop-notifications.js`, además de las versiones actualizadas de `layouts/app.blade.php`, `system-live-maintenance.js` y `TemporaryMaintenanceController.php`. La versión inicial de escritorio no necesitaba una migración adicional; la bandeja actual con permisos/equipos sí requiere las dos migraciones de `notificaciones-por-equipos.md`, junto con `app/Support/SystemNotifications.php`, `app/Models/SystemNotification.php` y `app/Models/User.php`. Pruebas adicionales: `node --test tests/JavaScript/desktop-notifications.test.cjs`.

Para los avisos de operaciones, incluir las versiones actualizadas de `app/Http/Controllers/AuthController.php`, `resources/views/reports/create.blade.php`, `resources/views/reports/show.blade.php`, `resources/views/beneficiaries/summary.blade.php` y `public/js/report-export.js`. El cambio a Reportado desde el informe de beneficiarios también genera un aviso al confirmarse. Pruebas de integración: `php artisan test --compact tests/Feature/DesktopActionNotificationsTest.php tests/Feature/TwoFactorAuthenticationTest.php`.

### Archivos y comandos

Respaldar antes de subir. Incluir la migración `database/migrations/2026_10_08_120000_create_user_presences_table.php`, `app/Models/UserPresence.php`, `app/Models/SystemSetting.php`, `app/Http/Controllers/SystemMaintenanceController.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Controllers/TemporaryMaintenanceController.php`, `routes/web.php`, las vistas `maintenance/index.blade.php`, `maintenance/live-notice.blade.php`, `layouts/app.blade.php`, `public/js/system-live-maintenance.js`, `public/css/system-maintenance.css` y `public/css/maintenance-banner.css`. Mantener el JS anterior `maintenance-banner.js`. Si se incluye el cambio de orden del menú, subir también `layouts/partials/configuration-menu.blade.php`.

Con consola:

```text
php artisan migrate --path=database/migrations/2026_10_08_120000_create_user_presences_table.php --force
php artisan optimize:clear
```

Sin consola, usar exclusivamente el modo preparado con su token privado:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=mantenimiento-en-vivo&token=TU_TOKEN
```

Este modo comprueba archivos, limpia cachés, ejecuta solo la migración de presencia y reconstruye cachés. No ejecuta seeders, importaciones, bloqueos o cambios en registros, beneficiarios, permisos o programaciones. Repetir conserva el estado de bloqueo. No ejecutar el modo general sin `only`. No subir la base local ni compartir el token.

Si falta la migración, el aviso sigue funcionando y el número muestra «No disponible», sin inventar cero. Verificar el funcionamiento con un administrador y un registrador en navegadores/sesiones separados. Probar el aviso/cancelación antes de programar un bloqueo real; asegurarse de que los usuarios ya cargaron el nuevo script.

Pruebas (SQLite en memoria, sin tocar la base real): `php vendor/bin/phpunit tests/Feature/SystemMaintenanceTest.php tests/Feature/TemporaryMaintenanceLiveTest.php` y `node --test tests/JavaScript/system-live-maintenance.test.cjs tests/JavaScript/maintenance-banner.test.cjs`.
