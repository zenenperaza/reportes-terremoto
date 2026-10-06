# Informes por Servicios

Consulta en **Informes → Informes por Servicios** (`/informes-por-servicios`). El catálogo maestro de servicios se conserva por separado en Configuración.

- **Servicios entregados**: solo asignaciones seleccionadas en registros que tienen beneficiarios y son visibles para el usuario. No es un catálogo de servicios programados. Un mismo servicio puede aparecer en varios proyectos o actividades.
- Filtros combinables por proyecto, sector, indicador, actividad, servicio, estado de la asignación, uno o varios períodos y fecha de atención desde/hasta. Todos los períodos de forma predeterminada. Se incluyen entregas históricas aunque la asignación esté inactiva.
- **Atenciones con servicio** cuenta las filas reales de `beneficiaries` vinculadas a los registros que seleccionaron ese servicio. No utiliza `total_beneficiaries`, no deduplica personas entre atenciones y no equivale a unidades entregadas. El servicio se guarda a nivel del registro, por lo que se asocia a sus beneficiarios. SIA actualmente no registra cantidades entregadas por servicio; no se supone una unidad por beneficiario. La cantidad disponible configurada se mantiene intacta y no se muestra como cantidad entregada.
- **Registros con servicio** cuenta encabezados de `reports`, no beneficiarios. Primera/última atención corresponden a `report_date` dentro de los filtros. La tabla agregada no muestra datos personales.
- **Ver configuración** abre la gestión de servicios de la actividad. **Ver beneficiarios** abre `/reportes` con la asignación exacta de servicio, los períodos y las fechas seleccionadas, incluyendo ambos estados de reporte. El filtro exacto se conserva al aplicar otros filtros y al exportar; respeta la visibilidad de cada usuario.
- La tabla utiliza DataTables con búsqueda, ordenación y paginación en español. Carga todas las asignaciones que coincidan con los filtros del informe, no solamente una página del servidor.
- La extensión Responsive repliega las columnas que no caben; el control **+** junto al servicio permite consultar el detalle y el enlace de beneficiarios. El botón **Exportar Excel** es verde y conserva todas las columnas en la descarga, incluidas las replegadas. El listado de beneficiarios usa también controles Responsive y conserva sus exportaciones completas.
- El acceso y el enlace del menú requieren `ver informes por servicios`. El botón **Exportar Excel** requiere además `exportar informes por servicios excel`. Estos permisos se asignan inicialmente solo al rol Administrador y se pueden asignar a otros roles desde Configuración. No conceden administración: el catálogo y **Ver configuración** siguen reservados a administradores. Los conteos y **Ver beneficiarios** respetan la visibilidad de registros del usuario.
- La descarga Excel contiene todos los resultados filtrados, no solamente la página visible. El botón de la tabla respeta también la búsqueda de DataTables y envía los IDs mediante POST con CSRF; el servidor vuelve a aplicar los filtros. Incluye conteos numéricos, primera/última atención, estados y una hoja de filtros. El texto se escribe como texto, nunca como fórmulas.

Ejecutar únicamente la nueva migración de permisos al desplegar:

`php artisan migrate --path=database/migrations/2026_10_06_120000_add_service_report_permissions.php --force`

No necesita seeders ni modificaciones de registros de beneficiarios. La migración crea dos permisos y los asigna al rol Administrador. En el despliegue deben incluirse los archivos nuevos y reconstruirse las cachés de rutas y vistas.

## Despliegue en cPanel sin consola

1. Haga un respaldo antes de actualizar y suba todos los archivos nuevos y modificados de esta funcionalidad, incluyendo:

   ```text
   app/Http/Controllers/TemporaryMaintenanceController.php
   app/Http/Controllers/ServicioProgramadoController.php
   app/Http/Controllers/PermissionController.php
   app/Http/Controllers/ReportController.php
   app/Services/ProgrammedServicesExcelExport.php
   app/Models/Report.php
   app/Models/ServicioActividad.php
   database/migrations/2026_10_06_120000_add_service_report_permissions.php
   resources/views/layouts/app.blade.php
   resources/views/reports/index.blade.php
   resources/views/servicios/index.blade.php
   resources/views/servicios/programados.blade.php
   public/css/report-datatable.css
   public/css/programmed-services.css
   public/js/service-report-table.js
   routes/web.php
   ```

   Conserve también las dependencias existentes: selector de períodos, DataTables, sus archivos Responsive y PhpSpreadsheet. Si los archivos públicos se publican en otra carpeta en cPanel, coloque los CSS/JS en el directorio público que utiliza la aplicación.

2. Abra el endpoint habitual, usando su token privado del servidor:

   ```text
   https://registros.asonacop.org/ejecutar-migraciones-temp?only=informes-servicios&token=TU_TOKEN
   ```

   **No lo ejecute sin `only=informes-servicios`**: el modo general tiene seeders y otras tareas anteriores. No combine este modo con opciones de importación o `only_cache`.

3. Este modo verifica que estén los archivos requeridos, limpia cachés, ejecuta **solo** la migración de permisos indicada, reconstruye configuración/rutas/vistas y limpia la caché de Spatie. No ejecuta seeders ni importaciones, ni modifica registros, beneficiarios, servicios entregados, períodos o cierres. Si la migración ya existe, `Nothing to migrate` es normal y repetir conserva los permisos asignados.

4. Compruebe código 0 en cada comando y `PERMISSION CACHE`, luego abra **Informes → Informes por Servicios** con un administrador. Compruebe filtros, beneficiarios, Responsive y Excel. Para otros roles, asigne `ver informes por servicios` y, si corresponde, `exportar informes por servicios excel`.

5. El despliegue no copia la base local ni el registro de prueba al servidor. El informe mostrará únicamente los servicios vinculados a registros del servidor. Mantenga el token privado y deshabilite/proteja el endpoint temporal al terminar según la política de su servidor.

## Verificación

`php vendor/bin/phpunit tests/Feature/ProgrammedServicesTest.php tests/Feature/ConfigurationNavigationTest.php tests/Feature/ReportFilterLayoutTest.php tests/Feature/ReportServiceColumnsTest.php tests/Feature/ReportOutputPermissionsTest.php tests/Feature/ReportNavigationTest.php`

`node --test tests/JavaScript/report-export.test.cjs`

`php vendor/bin/phpunit tests/Feature/TemporaryMaintenanceServiceReportsTest.php tests/Feature/TemporaryMaintenanceAssociatedIndicatorsTest.php tests/Feature/TemporaryMaintenancePeriodsTest.php tests/Feature/TemporaryMaintenancePermissionsTest.php`

Las pruebas PHP utilizan SQLite en memoria y no modifican la base de datos de la aplicación.
