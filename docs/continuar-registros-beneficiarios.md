# Continuar un listado de beneficiarios

En **Registros**, la acción **Continuar agregando beneficiarios** abre
`/reportes/{id}/continuar`. También aparece en el detalle del registro.

Se recuperan el proyecto, indicador, actividad, servicios, lugar, fecha y período
originales, junto con las personas ya guardadas. La sección de beneficiarios queda
habilitada; el contexto de la actividad permanece bloqueado. Cada nueva persona
se guarda inmediatamente en el mismo registro y actualiza sus totales.

Después del primer guardado en Nuevo registro, la dirección del navegador pasa a
la de continuación, para que recargar retome el listado. Si se cambia de pantalla
o se inicia otra sesión, puede recuperarse desde Registros. Los datos de una
persona que todavía no se haya guardado no se recuperan.

## Permisos y consistencia

- Requiere `registrar actividad` y `ver detalle de registros`, además del alcance
  de gestión del registro y un proyecto asignado y activo (administrador exceptuado
  de la asignación). No otorga permisos para editar ni eliminar datos existentes.
- Para coordinadores se respeta la configuración de edición de su equipo.
- No se permite continuar registros revisados ni períodos cerrados. Se comprueba
  de nuevo al guardar, dentro de la transacción.
- El servidor obtiene el contexto del registro de la URL; no confía en un
  `report_id`, proyecto, período o encabezado enviado por el navegador.
- Se mantienen los servicios y los indicadores asociados que se seleccionaron al
  crear el listado. La nueva persona se agrega a las copias asociadas existentes;
  si una copia no puede modificarse, se cancela toda la operación.
- Se conservan las validaciones de nombre, edad, cuidador, consentimiento y la
  recurrencia automática dentro del mismo proyecto.
  Los datos del cuidador son opcionales para menores de 18 años; si se ingresan,
  se comprueban sus longitudes y el parentesco seleccionado.
- No se unen automáticamente registros diferentes ni se modifican listados
  históricos. No requiere migraciones ni nuevos permisos.

## Archivos para el despliegue

- `app/Http/Controllers/ReportController.php`
- `app/Http/Requests/ContinueReportBeneficiaryRequest.php` (nuevo)
- `app/Http/Requests/Concerns/PreservesReportLocation.php`
- `app/Http/Requests/Concerns/ValidatesAssociatedIndicators.php`
- `app/Services/ReportContinuation.php` (nuevo)
- `app/Services/ReportDataTable.php`
- `resources/views/reports/create.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/reports/show.blade.php`
- `public/css/report-form-fixes.css`
- `routes/web.php`

Actualizar las cachés de rutas y vistas según el procedimiento de despliegue del
sistema; este cambio no ejecuta mantenimiento ni modifica la base de producción.

El despliegue conjunto con los permisos del Buzón está preparado en
[despliegue-buzon-registros-2026-11-04.md](despliegue-buzon-registros-2026-11-04.md),
mediante `only=actualizacion-buzon-registros`.

Pruebas específicas: `php vendor/phpunit/phpunit/phpunit tests/Feature/ReportContinuationTest.php --no-progress`.
