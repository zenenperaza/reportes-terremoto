# Columnas de indicadores, actividades y servicios

El listado `/reportes` separa Proyecto, Indicadores, Actividades, Servicios y N.º de servicios. Se cargan solo los servicios seleccionados en cada registro, no todos los disponibles del proyecto. Los registros anteriores sin esas asociaciones muestran etiquetas de ausencia y cero servicios.

## Interpretación del conteo

La relación `report_servicio_actividad` almacena selecciones, no cantidades entregadas. N.º de servicios cuenta selecciones por registro. `cantidad_disponible` pertenece al catálogo y **no** se utiliza como una entrega. En el consolidado cada fila corresponde a un beneficiario: un registro compartido se repite, por lo que no deben sumarse esas filas como entregas independientes. No se añadió captura de unidades ni se modificó el almacenamiento.

## Presentación y exportación

Se usa DataTables Responsive 3.0.8 con los archivos alojados localmente. Las columnas que no caben se despliegan desde el control junto a la fecha. Los botones Copiar, CSV, Excel, PDF e Imprimir conservan las columnas replegadas y excluyen Acciones. El CSV general del encabezado también incorpora la clasificación y el conteo. Los datos personales se siguen omitiendo del HTML para quienes no sean administradores, incluyendo los detalles responsive.

Referencia de la extensión: https://datatables.net/manual/extensions/responsive/

La tabla mantiene anchos mínimos legibles (especialmente para indicadores y servicios), de modo que Responsive repliegue columnas antes de comprimir su contenido. El control **+ / −** junto a la fecha permite abrir y cerrar el detalle; también recibe foco mediante teclado. Los controles de búsqueda, exportación y paginación se distribuyen en varias líneas cuando falta espacio. El CSS lleva una versión por fecha de modificación para evitar conservar estilos anteriores en caché.

## Archivos a subir a cPanel

- `app/Http/Controllers/ReportController.php`
- `app/Services/ReportDataTable.php`
- `app/Http/Middleware/RecordAuditLog.php`
- `resources/views/reports/index.blade.php`
- `resources/views/reports/_classification-columns.blade.php`
- `public/css/report-datatable.css`
- `public/js/report-export.js`
- `public/vendor/datatables/dataTables.responsive.min.js`
- `public/vendor/datatables/responsive.dataTables.min.css`

No requiere migraciones. Si se conservan vistas compiladas, use el flujo de mantenimiento existente con `only_cache=1` después de subir todos los archivos. Conserve el `.env`, `APP_KEY` y los datos del servidor.

## Procesamiento del consolidado en el servidor

El consolidado (administradores y coordinadores) carga sus filas por AJAX desde `GET /reportes?draw=...` usando el mismo permiso de acceso del listado. El HTML inicial no consulta ni incluye todos los beneficiarios. El historial personal mantiene su comportamiento anterior.

La paginación, búsqueda global y ordenamiento se ejecutan en SQL. Hay páginas de 15, 25, 50 y 100 filas, con un límite de 100 aplicado también en el servidor. Los totales solo incluyen registros autorizados; `recordsFiltered` aplica además los filtros superiores y la búsqueda. Las columnas de ordenamiento se resuelven mediante una lista permitida del servidor, sin confiar en nombres enviados por el navegador. Servicios, que contiene varios valores, no admite ordenamiento, pero sí búsqueda. El texto de búsqueda no se guarda en la bitácora y los datos personales no se devuelven ni se buscan para usuarios no administradores.

Los botones **Copiar, CSV, Excel, PDF e Imprimir** solicitan todos los registros que coincidan con los filtros superiores y la búsqueda actual, manteniendo el orden elegido. Sin filtros ni búsqueda incluyen todos los registros autorizados. La exportación no cambia la página visible ni desactiva el procesamiento del listado en el servidor; incluye también las columnas replegadas por Responsive, pero no Acciones. La consulta de exportación reutiliza la misma lógica de permisos y filtros, sin LIMIT/OFFSET, y lee lotes de 500 filas dentro de una transacción. Excel y PDF verifican su permiso también en el servidor.

La descarga se prepara únicamente al pulsar un botón, mostrando un estado de progreso. Los formatos se generan en el navegador, por lo que una exportación muy grande puede requerir más tiempo y memoria en el equipo del usuario. La impresión abre una ventana al hacer clic; Copiar permite usar Ctrl+C si el navegador no autoriza copiar automáticamente. Los fallos se informan sin exportar silenciosamente solo la página visible.

El CSV general del encabezado sigue funcionando de forma independiente, con los filtros superiores (no aplica la búsqueda interna de DataTables).

Protocolo utilizado: [DataTables server-side processing](https://datatables.net/manual/core/server-side).

## Verificación

`php artisan test --filter='ReportServiceColumnsTest|ReportOutputPermissionsTest|AuditLogTest|RolePermissionManagementTest'` comprueba paginación, límites, búsqueda, filtros, ordenamiento, conteo, privacidad, escape HTML, columnas, servicios seleccionados, registros antiguos, CSV y permisos. La exportación se verifica con 520 filas, incluyendo el cambio de lote, resultados filtrados y vacíos, todos los formatos y permisos por formato.

`node --test tests/JavaScript/report-export.test.cjs` comprueba la conexión de los cinco botones, envío de filtros/búsqueda/orden, exportación de 68 resultados completos, errores, fórmulas y ventanas de impresión bloqueadas. No sustituye la comprobación visual ni la apertura manual de archivos en el navegador del usuario.
