# Proyectos en Informes generales y por indicadores

El primer filtro de `/informes-generales` y `/informes-por-indicadores` permite elegir uno o varios proyectos. Sin selección se incluyen todos los registros visibles, incluidos los históricos sin proyecto. **Seleccionar todos los proyectos** permite quitar proyectos individuales después.

Al cerrar el selector o pulsar **Aplicar proyectos**, se recarga el informe conservando Reportado y reiniciando los filtros posteriores. Períodos, Indicadores Resumen, indicadores, fechas disponibles, ubicaciones, sectores, lugares, sexo, edades y recurrencia se obtienen únicamente de los proyectos seleccionados y del estado de reporte. No se incluyen períodos sin registros del proyecto seleccionado.

La consulta del informe, sus cinco gráficos, todas las tarjetas y la exportación Excel comparten el mismo filtro. El Excel identifica también los proyectos elegidos en Filtros aplicados. Las consultas asíncronas de fechas y municipios/parroquias conservan los proyectos; cambiar Reportado o limpiar filtros también conserva la selección.

Los proyectos disponibles se limitan a los asignados al usuario o con registros que ya puede consultar. La selección nunca amplía la visibilidad de equipos ni los permisos de exportación. Los indicadores excluidos del informe de beneficiarios siguen contando en ambos informes. No se modifica el formulario de registro.

En Informes por indicadores, el selector permanece en esa pantalla al aplicar proyectos. Las tarjetas, los totales de grupos y cada indicador, y las hojas Resumen, Grupos e Indicadores del Excel respetan la selección, incluso si un mismo indicador está configurado en varios proyectos. El Excel identifica los proyectos en Filtros aplicados. Al cambiar proyectos se conserva Reportado y se reinician los filtros posteriores, igual que en Informes generales.

El Excel de indicadores incluye además el código y nombre del proyecto en el nombre del archivo y en el encabezado de las tres hojas. Se utiliza el alias, o la descripción si no hay alias. Sin selección se identifica como «Todos los proyectos»; al seleccionar varios se muestran todos en los encabezados y filtros. Para mantener un nombre de archivo manejable, este contiene una referencia de hasta 120 caracteres a los dos primeros proyectos y «y-N-mas» si hay otros, seguida de la fecha y hora. No cambia los datos ni las posiciones de las tablas. El nombre del Excel de Informes generales se mantiene sin cambios.

## Despliegue

No requiere migraciones. Archivos de esta funcionalidad:

```text
app/Http/Controllers/GeneralReportController.php
app/Http/Controllers/IndicatorReportController.php
app/Services/ReportSummaryExcelExport.php
resources/views/general-reports/index.blade.php
resources/views/indicator-reports/index.blade.php
resources/views/general-reports/partials/filters-and-summary.blade.php
resources/views/general-reports/partials/reported-filter.blade.php
resources/views/general-reports/partials/project-filter.blade.php
public/js/general-project-filter.js
public/js/general-report-locations.js
```

Suba también los archivos nuevos pendientes de las tarjetas, especialmente `resources/views/general-reports/partials/condition-cards.blade.php`, y cualquier otro cambio aún no publicado. Renueve las cachés habituales y recargue con Ctrl + F5. Este trabajo modifica el código local, no publica automáticamente en el servidor.
