# Despliegue SIA — 13 de octubre de 2026

Este modo reúne los cambios pendientes de mantenimiento en vivo, usuarios conectados, orden del menú y notificaciones (incluida la corrección de Probar notificación). No incluye cambios de WhatsApp ni Minecraft. No hace falta ejecutar los modos de presencia y notificaciones por separado.

## Antes de subir

1. Respaldar la base de datos y los archivos actuales del servidor.
2. Si se necesita bloquear el sistema durante la subida, hacerlo desde Mantenimiento. El controlador de despliegue no activa, cancela ni reprograma bloqueos.
3. Subir todos los archivos de ejecución indicados abajo, incluidos los nuevos que aparecen con `??` en `git status`. Los archivos sin seguimiento no aparecen en `git diff` normal; un despliegue basado solo en archivos ya versionados los omitiría. No se hizo `git add`, commit ni push.
4. No subir la base local, `.env`, credenciales, `storage` local ni cachés compiladas de `bootstrap/cache`. Conservar el token privado y la configuración del servidor.

## Archivos de ejecución

Rutas relativas a la raíz del proyecto Laravel. Los archivos de `public/` deben ir en la raíz pública correspondiente del hosting.

```text
app/Http/Controllers/AuthController.php
app/Http/Controllers/PermissionController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/SystemMaintenanceController.php
app/Http/Controllers/TemporaryMaintenanceController.php
app/Models/SystemSetting.php
app/Models/User.php
app/Models/SystemNotification.php
app/Models/UserPresence.php
app/Support/SystemNotifications.php
database/migrations/2026_10_08_120000_create_user_presences_table.php
database/migrations/2026_10_09_120000_create_system_notifications_table.php
database/migrations/2026_10_09_120100_add_receive_notifications_permission.php
public/css/maintenance-banner.css
public/css/system-maintenance.css
public/js/desktop-notifications.js
public/js/system-live-maintenance.js
public/js/pwa.js
public/js/report-export.js
public/service-worker.js
resources/views/beneficiaries/summary.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/partials/configuration-menu.blade.php
resources/views/layouts/partials/desktop-notifications.blade.php
resources/views/maintenance/index.blade.php
resources/views/maintenance/live-notice.blade.php
resources/views/reports/create.blade.php
resources/views/reports/show.blade.php
routes/web.php
```

También deben conservarse `routes/maintenance.php` y su carga desde `bootstrap/app.php`, ya presentes en el despliegue anterior. Las pruebas y los documentos no son necesarios para ejecutar el sistema en producción.

La comprobación del controlador detecta archivos ausentes; no compara sus versiones. Sobrescribir con la versión actual todos los archivos de esta lista, no solo los nuevos.

## Ejecutar tras completar la subida

Usar exclusivamente este modo, sustituyendo `TU_TOKEN` por el token privado ya configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-sia&token=TU_TOKEN
```

No ejecutar la ruta sin `only`: el modo general anterior incluye operaciones ajenas a este despliegue. No combinar este modo con `only_cache`, `import_excel`, `apply` o `decisions`, incluso si su valor es `0`.

`actualizacion-sia` comprueba primero los archivos y después ejecuta, en orden:

1. `optimize:clear` y `cache:clear`.
2. Solo la migración de `user_presences`.
3. Solo la migración de `system_notifications`.
4. Solo la migración que agrega **Recibir notificaciones**, asignado inicialmente a `admin`, `coordinator` y `reporter`.
5. `config:cache`, `route:cache`, `view:cache` y limpieza de caché de permisos.

Laravel omite migraciones ya aplicadas. Repetir este modo no vuelve a asignar permisos retirados, no borra avisos ni cambia bloqueos o programaciones. No ejecuta seeders, importaciones, migraciones generales ni cambios en registros, beneficiarios, equipos, servicios entregados, períodos o cierres. Si falla un comando se detiene; los comandos previos completados no se revierten automáticamente. Corregir el error antes de repetir.

## Comprobar al terminar

- La página debe terminar sin errores y mostrar `PERMISSION CACHE: caché de roles y permisos limpiada correctamente.`
- Recargar SIA con **Ctrl + F5**. Comprobar el orden Usuarios → Proyectos → Configuraciones y el conteo del footer con administrador, coordinador y registrador.
- Comprobar presencia y aviso de mantenimiento sin programar un bloqueo real innecesario. Habilitar el sistema manualmente si se bloqueó para la subida.
- Desde la campana, Probar notificación debe mostrar un resultado debajo del botón. Cada usuario habilita personalmente el permiso del navegador. No hacer operaciones de prueba sobre registros reales.
- Los administradores autorizados reciben avisos de accesos; coordinadores/registradores autorizados, cambios de otros integrantes de sus equipos. Los permisos de registros no se amplían.
- SIA debe seguir abierto para recibir avisos nuevos: no se implementó Web Push con el sistema cerrado.

El controlador solo quedó preparado en local; no se ejecutó en el servidor.
