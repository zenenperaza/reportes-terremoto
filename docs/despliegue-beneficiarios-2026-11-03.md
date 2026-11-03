# Despliegue de beneficiarios y filtro Proyectos — 3 de noviembre de 2026

## Antes de ejecutar

1. Respalde la base de datos del servidor y conserve una copia del código actualmente publicado.
2. Active el mantenimiento de SIA desde su pantalla habitual y permita que los usuarios guarden sus registros antes del bloqueo. El controlador temporal no cambia ni retira ese bloqueo.
3. Suba todos los archivos de ejecución pendientes y la migración del cuidador. No basta con subir únicamente el controlador temporal. Incluya especialmente los archivos nuevos sin seguimiento de Git; los tests y documentos no son necesarios en producción.

Archivos de esta actualización:

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/BeneficiaryLookupController.php
app/Http/Controllers/ReportController.php
app/Http/Middleware/RecordAuditLog.php
app/Http/Requests/StoreBeneficiaryEntryRequest.php
app/Http/Requests/StoreReportRequest.php
app/Http/Requests/UpdateBeneficiaryRequest.php
app/Http/Requests/Concerns/ValidatesBeneficiaryCaregiver.php
app/Services/BeneficiaryRecurrence.php
app/Models/Beneficiary.php
config/reports.php
database/migrations/2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php
resources/views/reports/create.blade.php
resources/views/reports/index.blade.php
resources/views/reports/show.blade.php
public/css/beneficiary-entry.css
public/css/report-datatable.css
public/js/beneficiary-id-autofill.js
public/js/beneficiary-voice.js
public/js/beneficiary-recurrence.js
public/js/record-project-filter.js
routes/web.php
```

También deben existir en el servidor `routes/maintenance.php` y `app/Http/Requests/UpdateBeneficiaryAttentionRequest.php`, dependencias anteriores comprobadas por el controlador. Las actualizaciones anteriores, incluida la columna nacionalidad, deben estar instaladas; este modo no instala todas las migraciones pendientes.

## Ejecutar una vez después de subir los archivos

Abra esta dirección sustituyendo `TU_TOKEN` por el valor de `SERVER_MAINTENANCE_TOKEN` ya configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-beneficiarios&token=TU_TOKEN
```

Use exactamente `only=actualizacion-beneficiarios`. No abra la ruta sin `only`: el modo general anterior incluye otras operaciones. No agregue `only_cache`, `import_excel`, `apply` ni `decisions`.

El modo comprueba que los archivos existan antes de ejecutar cambios, pero no puede verificar que el contenido subido sea la versión más reciente. Luego ejecuta, en orden:

1. `optimize:clear` y `cache:clear`.
2. Solo la migración `2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php`, con `--force`.
3. `config:cache`, `route:cache` y `view:cache`.
4. Limpieza de caché de roles y permisos, sin modificar sus asignaciones.

Se crean tres columnas opcionales del cuidador. No se rellenan datos históricos, no se recalcula su recurrencia y no se ejecutan seeders, importaciones, correcciones de parroquias ni otras migraciones. Una migración ya registrada se omite al repetir el modo.

Si falta un archivo o falla un comando, el proceso se detiene. Mantenga el bloqueo, corrija el problema y vuelva a ejecutar este mismo modo. No ejecute rollback ni borre filas de la tabla migrations por iniciativa propia.

## Verificar antes de habilitar el sistema

- La respuesta debe terminar con todos los comandos en código 0 y el aviso de caché de permisos limpiada.
- Recargue con **Ctrl + F5**. Compruebe nombre obligatorio, cédula primero y autocompletado autorizado.
- Para menores de 18 años, compruebe los datos del cuidador y el selector de parentesco.
- Compruebe la recurrencia dentro del mismo proyecto y que otro proyecto no la active.
- En `/reportes`, pruebe Proyectos, selección múltiple, Seleccionar todos y exportaciones.
- Pruebe el dictado con micrófono y permisos del navegador. Sus resultados deben revisarse antes de guardar.
- Retire el bloqueo manualmente cuando termine la revisión. Retire o deshabilite la ruta temporal cuando ya no sea necesaria.

El controlador se ha preparado localmente; no se ha ejecutado contra el servidor.
