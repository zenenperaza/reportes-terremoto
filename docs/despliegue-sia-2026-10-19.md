# Despliegue SIA — 19 de octubre de 2026

Este despliegue incluye el período en el Excel de beneficiarios, los botones verdes de Excel junto a los filtros de informes generales/indicadores/servicios, las etiquetas cortas del gráfico del panel y la selección obligatoria del proyecto en registros nuevos. No incluye cambios de WhatsApp, Minecraft ni correcciones de datos de parroquias.

## Antes de subir

1. Respaldar la base de datos y los archivos actuales del servidor.
2. Si se necesita bloquear el sistema durante la subida, usar la opción de Mantenimiento. El controlador no activa ni cancela bloqueos.
3. Subir todas las versiones actuales indicadas abajo, incluido el archivo nuevo `public/css/report-excel-button.css`. No depender exclusivamente de los archivos ya registrados en Git.
4. No subir ni importar `docs/bdd/asonacop_registros (15-10-26).sql`, la base local, `.env`, credenciales, `storage` local o las cachés compiladas de `bootstrap/cache`.

## Archivos de ejecución

Rutas relativas al proyecto Laravel. Los archivos de `public/` van en la raíz pública correspondiente del hosting.

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/BeneficiaryReportController.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/ReportController.php
app/Http/Requests/StoreReportRequest.php
app/Http/Requests/StoreBeneficiaryEntryRequest.php
public/css/programmed-services.css
public/css/report-excel-button.css
public/js/general-report-locations.js
resources/views/dashboard.blade.php
resources/views/general-reports/index.blade.php
resources/views/general-reports/partials/excel-export.blade.php
resources/views/general-reports/partials/filters-and-summary.blade.php
resources/views/indicator-reports/index.blade.php
resources/views/reports/create.blade.php
resources/views/servicios/programados.blade.php
```

La corrección de `database/seeders/ReferenceDataSeeder.php` solo previene el nombre incorrecto en futuras cargas del catálogo. Puede conservarse en el código, pero **no ejecutar ese seeder** durante esta actualización. La migración de la parroquia se eliminó del código local por solicitud del usuario. No se ejecutó rollback ni se revirtió su nombre en la base local. La parroquia del servidor ya fue corregida manualmente y no debe modificarse.

También deben existir las dependencias del despliegue anterior: `app/Services/ReportSummaryExcelExport.php`, `app/Services/ProgrammedServicesExcelExport.php`, `public/js/service-report-table.js`, `routes/web.php`, `routes/maintenance.php` y PhpSpreadsheet instalado. Las pruebas y documentos no son necesarios para ejecutar SIA en producción.

La comprobación detecta archivos ausentes, no versiones antiguas: subir todos los archivos de la lista, no solo los nuevos. No se hizo `git add`, commit, push ni despliegue remoto.

## Ejecutar después de completar la subida

Utilizar únicamente este modo, sustituyendo `TU_TOKEN` por el token privado configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-informes&token=TU_TOKEN
```

No ejecutar la ruta sin `only`. El modo general anterior contiene operaciones ajenas a esta actualización. `only=actualizacion-sia` es el modo anterior de presencia y notificaciones, no el de este despliegue.

El modo nuevo comprueba archivos y ejecuta solo `optimize:clear`, `cache:clear`, `config:cache`, `route:cache`, `view:cache` y limpieza de caché de permisos. No ejecuta migraciones, seeders, importaciones ni correcciones SQL. No se combina con `only_cache`, `import_excel`, `apply` o `decisions`, incluso con valor `0`.

No modifica registros, beneficiarios, parroquias, asignaciones de proyectos, permisos, períodos, cierres ni bloqueos. Si falla un comando se detiene; los anteriores completados no se revierten automáticamente. Corregir el error antes de repetir. Repetir este modo solo reconstruye cachés.

## Comprobar al finalizar

- La respuesta debe mostrar todos los comandos con código `0` y terminar con `PERMISSION CACHE: caché de roles y permisos limpiada correctamente.`
- Recargar SIA con **Ctrl + F5** y habilitar el sistema manualmente si se bloqueó para la subida.
- Verificar la columna Período del Excel de beneficiarios y los botones verdes junto a los filtros en los otros tres informes, con los permisos existentes. Las descargas corresponden a los filtros del informe generado. Servicios conserva la vista elegida y la búsqueda de la tabla en todas sus páginas.
- En el panel, el gráfico usa el alias del proyecto y el nombre corto del indicador; si faltan, utiliza las etiquetas anteriores. No cambian agrupaciones ni totales.
- En un registro nuevo, un solo proyecto disponible se selecciona automáticamente. Con dos o más queda vacío; se exige elegir uno para guardar. Los errores conservan la elección y la edición de registros históricos mantiene su validación anterior.
- La parroquia no requiere ejecutar SQL ni migraciones.

El controlador solo quedó preparado en local. No se ejecutó en el servidor.
