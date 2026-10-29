# Despliegue: filtro de proyectos en el informe de beneficiarios

Estos cambios no requieren migraciones ni importaciones. No se debe subir ni importar la base local, archivos SQL, `.env`, credenciales, `storage` local o cachés compiladas de `bootstrap/cache`. Conservar la configuración y los datos del servidor.

## Archivos de esta actualización

Subir las versiones actualizadas de:

- `app/Http/Controllers/BeneficiaryReportController.php`
- `app/Http/Controllers/TemporaryMaintenanceController.php`
- `resources/views/beneficiaries/summary.blade.php`
- `resources/views/reports/partials/period-filter.blade.php`
- `public/css/beneficiary-summary.css`
- `public/js/beneficiary-project-filter.js` (archivo nuevo, imprescindible)
- `public/js/general-report-locations.js`
- `public/js/report-period-dates.js`

El modo de mantenimiento verifica la existencia de estos archivos y de las dependencias de informes ya desplegadas. La comprobación no compara versiones: es necesario subir todos los archivos modificados, aunque ya existan en el servidor.

## Limpiar y reconstruir cachés

Después de completar la subida, abrir:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-informes&token=TU_TOKEN
```

Sustituir `TU_TOKEN` por el `SERVER_MAINTENANCE_TOKEN` privado ya configurado en el servidor. No compartir el token ni subir el `.env` local.

El modo comprueba los archivos antes de ejecutar, en este orden:

```text
optimize:clear
cache:clear
config:cache
route:cache
view:cache
```

Al finalizar limpia la caché de permisos. No ejecuta migraciones, seeders, importaciones, correcciones SQL ni cambios de datos, asignaciones o permisos. No activa ni cancela el mantenimiento del sistema.

No abrir la ruta sin `only=actualizacion-informes`: el modo general antiguo contiene operaciones ajenas a esta subida. No combinar con `only_cache`, `import_excel`, `apply` o `decisions`, ni siquiera con valor `0`.

Si un comando falla, el proceso se detiene y devuelve un error. Los comandos anteriores no se revierten automáticamente: corregir el problema y repetir el mismo modo. Repetirlo solo reconstruye las cachés.

## Comprobación después de la subida

Recargar SIA con Ctrl + F5 y abrir `/informe-beneficiarios`. Proyectos debe aparecer primero, con Todos los proyectos de forma predeterminada, selección múltiple y Seleccionar todos los proyectos. Al cerrar la lista o aplicar la selección se conserva Reportado y se reinician los filtros posteriores.

Comprobar que los períodos, indicadores, Indicadores Resumen, ubicaciones, lugares, recurrencia y fechas pertenecen a los proyectos seleccionados. Cambiar Reportado o Período debe conservar los proyectos. Verificar también el Excel y el acceso con un usuario no administrador.
