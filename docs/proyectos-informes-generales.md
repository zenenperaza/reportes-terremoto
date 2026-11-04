# Proyectos en Informes generales

El primer filtro de `/informes-generales` permite elegir uno o varios proyectos. Sin selección se incluyen todos los registros visibles, incluidos los históricos sin proyecto. **Seleccionar todos los proyectos** permite quitar proyectos individuales después.

Al cerrar el selector o pulsar **Aplicar proyectos**, se recarga el informe conservando Reportado y reiniciando los filtros posteriores. Períodos, Indicadores Resumen, indicadores, fechas disponibles, ubicaciones, sectores, lugares, sexo, edades y recurrencia se obtienen únicamente de los proyectos seleccionados y del estado de reporte. No se incluyen períodos sin registros del proyecto seleccionado.

La consulta del informe, sus cinco gráficos, todas las tarjetas y la exportación Excel comparten el mismo filtro. El Excel identifica también los proyectos elegidos en Filtros aplicados. Las consultas asíncronas de fechas y municipios/parroquias conservan los proyectos; cambiar Reportado o limpiar filtros también conserva la selección.

Los proyectos disponibles se limitan a los asignados al usuario o con registros que ya puede consultar. La selección nunca amplía la visibilidad de equipos ni los permisos de exportación. Los indicadores excluidos del informe de beneficiarios siguen contando en Informes generales. No se modifica el formulario de registro ni se añade este control a Informes por indicadores.

## Despliegue

No requiere migraciones. Archivos de esta funcionalidad:

```text
app/Http/Controllers/GeneralReportController.php
app/Http/Controllers/IndicatorReportController.php
app/Services/ReportSummaryExcelExport.php
resources/views/general-reports/index.blade.php
resources/views/general-reports/partials/filters-and-summary.blade.php
resources/views/general-reports/partials/reported-filter.blade.php
resources/views/general-reports/partials/project-filter.blade.php
public/js/general-project-filter.js
public/js/general-report-locations.js
```

Suba también los archivos nuevos pendientes de las tarjetas, especialmente `resources/views/general-reports/partials/condition-cards.blade.php`, y cualquier otro cambio aún no publicado. Renueve las cachés habituales y recargue con Ctrl + F5. Este trabajo modifica el código local, no publica automáticamente en el servidor.
