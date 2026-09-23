# Permisos independientes de registros y beneficiarios

Las acciones usan cuatro permisos configurables desde la edición de roles:

- editar registros: datos compartidos del grupo.
- eliminar registros: eliminar el registro completo.
- editar beneficiarios: editar los datos personales y la atención de una persona.
- eliminar beneficiarios: eliminar una persona del registro.

Se conservan las restricciones de propietario y registro revisado de la edición individual. Eliminar el último beneficiario sigue siendo una acción reservada a administradores y requiere además eliminar registros.

## Subir al servidor

Respaldar la base de datos y subir conjuntamente estos archivos desde la raíz del proyecto:

1. app/Http/Controllers/TemporaryMaintenanceController.php
2. database/migrations/2026_09_23_180000_add_beneficiary_management_permissions.php
3. app/Http/Controllers/PermissionController.php
4. app/Http/Controllers/ReportController.php
5. app/Http/Requests/UpdateBeneficiaryAttentionRequest.php
6. app/Http/Requests/Concerns/PreservesReportLocation.php
7. routes/web.php
8. resources/views/reports/show.blade.php
9. resources/views/reports/create.blade.php
10. public/css/beneficiary-records.css

Sin consola, usar el endpoint existente con el token privado del servidor:

```text
/ejecutar-migraciones-temp?only=permisos-beneficiarios&token=TU_TOKEN
```

Sustituir TU_TOKEN por SERVER_MAINTENANCE_TOKEN del servidor, codificado para URL. No compartir la URL con el token real.

Este modo ejecuta solamente la nueva migración y la limpieza/reconstrucción de cachés. No ejecuta otras migraciones, seeders ni importadores. No combinarlo con only_cache, import_excel, apply o decisions. No usar el endpoint sin only para este despliegue, porque el modo general conserva sus otras tareas.

La migración conserva los permisos de registros existentes y asigna inicialmente los nuevos permisos a los mismos roles y usuarios que tenían la acción correspondiente. Después pueden desmarcarse de forma independiente desde Roles. No cambia registros ni beneficiarios. Laravel registra la migración, por lo que repetir este modo no vuelve a aplicar asignaciones de una migración ya ejecutada.

Con consola:

```sh
php artisan migrate --path=database/migrations/2026_09_23_180000_add_beneficiary_management_permissions.php --force
php artisan optimize:clear
php artisan permission:cache-reset
```

Verificar que MIGRATE termine con código 0 y que aparezca PERMISSION CACHE. Revisar los cuatro permisos desde Roles. Una vez finalizado el mantenimiento, deshabilitar el acceso temporal según la política del servidor.

## Pruebas locales

```sh
php artisan test --compact --filter="TemporaryMaintenancePermissionsTest|BeneficiaryAttentionEditingTest"
```
