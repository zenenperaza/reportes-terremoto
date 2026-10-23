# Lugares del catálogo por proyecto

El catálogo «Nombres del lugar» no cambia. Los proyectos seleccionan uno o varios lugares existentes; un mismo lugar puede asignarse a varios proyectos.

## Despliegue

Preparado el 22 de octubre de 2026. Antes de subir, respaldar la base de datos y los archivos del servidor. Si se necesita bloquear SIA durante la subida, hacerlo desde Mantenimiento; este controlador no cambia bloqueos ni avisos programados.

No subir ni importar la base local, los SQL de `docs/bdd`, `.env`, credenciales, `storage` local ni las cachés de `bootstrap/cache`.

Subir las versiones actuales de estos archivos (los de `public/` van en la raíz pública correspondiente del hosting):

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/ProyectoController.php
app/Http/Controllers/ReportController.php
app/Http/Requests/StoreReportRequest.php
app/Http/Requests/StoreBeneficiaryEntryRequest.php
app/Http/Requests/Concerns/ValidatesProjectPlaces.php
app/Http/Requests/Concerns/PreservesReportLocation.php
app/Models/Proyecto.php
app/Models/PlaceName.php
database/migrations/2026_10_22_120000_create_project_place_assignments_table.php
resources/views/proyectos/create.blade.php
resources/views/proyectos/edit.blade.php
resources/views/proyectos/form.blade.php
resources/views/proyectos/show.blade.php
resources/views/reports/create.blade.php
public/js/associated-indicators.js
routes/web.php
routes/maintenance.php
```

Se incluye el ajuste que permite seleccionar como principal un indicador que también esté asociado a otro. La comprobación del controlador detecta archivos ausentes, no versiones antiguas: subir todos los archivos de la lista. Las pruebas y esta guía no son necesarias para ejecutar SIA.

Después de completar la subida, abrir esta dirección sustituyendo `TU_TOKEN` por el valor privado de `SERVER_MAINTENANCE_TOKEN` ya configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=lugares-proyecto&token=TU_TOKEN
```

No ejecutar la ruta sin `only`. Los modos generales antiguos contienen operaciones ajenas a esta subida. `only=actualizacion-informes` no ejecuta la nueva migración y no sustituye a este modo.

El modo `lugares-proyecto` comprueba todos los archivos antes de ejecutar comandos. Después ejecuta únicamente, en este orden:

```text
optimize:clear
cache:clear
migrate --path=database/migrations/2026_10_22_120000_create_project_place_assignments_table.php --force
config:cache
route:cache
view:cache
limpieza de caché de permisos
```

No se combina con `only_cache`, `import_excel`, `apply` o `decisions`, aunque se envíen con valor `0`. Si un comando falla, el proceso se detiene; los anteriores completados no se revierten automáticamente. Corregir el error antes de repetir. Una migración ya aplicada se omite al repetir y no se borran las asignaciones guardadas.

Si se dispone de terminal, como alternativa al enlace se puede ejecutar la migración específica y limpiar las vistas:

```sh
php artisan migrate --path=database/migrations/2026_10_22_120000_create_project_place_assignments_table.php --force
php artisan view:clear
```

La migración solo crea `lugar_proyecto`. No asigna automáticamente lugares, no modifica parroquias y no cambia registros históricos ni el catálogo.

Después, editar cada proyecto en **Proyectos → Editar → Lugares del proyecto**, elegir sus lugares y guardar. La selección se limita a los estados y municipios configurados. Si no se eligen lugares, ese proyecto no ofrecerá ninguno del catálogo al registrar; no se interpretará como «todos».

La respuesta del controlador debe mostrar todos los comandos con código `0` y terminar con `PERMISSION CACHE: caché de roles y permisos limpiada correctamente.` Recargar SIA con **Ctrl + F5**, configurar los proyectos y probar el cambio de proyecto en el registro antes de retirar el mantenimiento manualmente. No es necesario guardar beneficiarios de prueba en producción.

Los nuevos registros validan la asignación también en el servidor. Los usuarios mantienen sus restricciones geográficas. La opción de atención comunitaria por parroquia sigue funcionando como antes.

Al editar un registro anterior se puede conservar exactamente su ubicación guardada, aunque el lugar se haya desasignado, modificado o eliminado. Cambiar la ubicación o el proyecto exige una asignación válida; no se autoriza crear nuevos registros usando una ubicación antigua desasignada.

Las pruebas de esta funcionalidad usan SQLite en memoria, no la base de datos de los usuarios.

El controlador solo quedó preparado en local: no se ejecutó en el servidor ni se hizo commit, push o despliegue remoto.
