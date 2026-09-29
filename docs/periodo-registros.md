# Período de los registros

El período configurado en **Configuración → Configuraciones** se guarda en
`reports.reporting_period` como `AAAA-MM` al crear registros (tanto con el
formulario completo como al guardar beneficiarios individualmente).

- Es independiente de `report_date` (fecha de atención).
- El servidor asigna el período; no toma un período editable enviado por el cliente.
- Si cambió la configuración mientras estaba abierto un formulario nuevo, se
  solicita recargar antes de crear el registro.
- Editar registros, agregar beneficiarios a un grupo existente o separar un
  beneficiario conserva el período original.
- Los registros anteriores quedan con `NULL`. No hay actualización retroactiva.
- Los listados e informes permiten elegir un período, todos o «Sin período asignado».
  Las exportaciones y la acción de marcar reportados respetan este filtro.
- En los informes de beneficiarios, generales y por indicadores, el período ocupa
  una fila completa antes de los demás filtros. Sin selección explícita en la URL
  se aplica el período actual configurado, incluso si no tiene registros. Elegir
  «Todos los períodos» mantiene todos, incluidas las exportaciones y el reporte al
  donante. Cambiar el estado Reportado conserva el período seleccionado.

## Despliegue

Subir los archivos modificados y nuevos. Antes de utilizar la aplicación ejecutar:

```sh
php artisan migrate --path=database/migrations/2026_09_28_120000_add_reporting_period_to_reports.php --force
```

La migración agrega una columna nullable y su índice, sin cambiar registros
existentes. Está incluida también en el flujo general del controlador de
mantenimiento temporal; ese flujo ejecuta otras tareas previas, por lo que el
comando específico es preferible cuando se dispone de consola.

No ejecutar el rollback en producción: eliminaría los períodos ya guardados.

## Administrador y cierre de períodos

Configuraciones muestra el período actual, los usados por registros y los
guardados al cambiar de período. «Cerrado» se aplica al pulsar Guardar; puede
desmarcarse para reabrir. Solo administradores pueden cambiar este estado.

El cierre impide crear registros en ese período, agregar beneficiarios, editar
o separar beneficiarios, eliminar registros/personas y marcar revisado/reportado.
Se mantienen consultas y exportaciones. Una actualización masiva que incluya
pendientes de un período cerrado se rechaza por completo, sin cambios parciales.
Los bloqueos de fila y transacciones sincronizan las escrituras con el cierre.
Los registros sin período no se reasignan ni cierran automáticamente.

Después de la migración anterior ejecutar también:

```sh
php artisan migrate --path=database/migrations/2026_09_28_130000_create_reporting_periods_table.php --force
```

La migración conserva los períodos conocidos inicialmente abiertos. No cierra
ningún período ni modifica registros existentes. También está incluida en el
controlador de mantenimiento temporal. Su rollback elimina el historial de cierres.

## Actualización del servidor sin consola

1. Genere y descargue un respaldo de la base de datos antes de actualizar.
2. Suba todos los archivos nuevos y modificados de esta actualización, incluidas
   ambas migraciones, `ReportingPeriod.php`, `ReportPeriod.php`,
   `ReportPeriodTransaction.php`, la vista parcial `period-filter.blade.php`,
   `public/css/system-configuration.css` y `public/js/navigation-disclosure.js`.
   No reemplace el `.env`, la base de datos ni los archivos de `storage` del servidor.
3. Abra esta ruta en el dominio donde está instalado SIA, reemplazando `TU_TOKEN`
   por el valor de `SERVER_MAINTENANCE_TOKEN` de ese servidor:

   ```text
   /ejecutar-migraciones-temp?token=TU_TOKEN&only=periodos
   ```

   No use el modo general ni lo combine con `only_cache`, `import_excel`, `apply`
   o `decisions`. El modo `periodos` comprueba archivos requeridos, limpia cachés,
   aplica primero la columna y después el historial de períodos, y reconstruye
   configuración, rutas y vistas. No ejecuta seeders ni importa Excel.
4. Compruebe que todos los comandos indiquen código `0`. Si uno falla, el proceso
   se detiene. Corrija el error y repita la misma URL; las migraciones ya aplicadas
   se omiten y no se sobrescriben cierres existentes.
5. Verifique Configuraciones, el formulario de registro y los filtros de informes.
   Los registros anteriores sin período seguirán sin período: se encuentran con
   «Todos los períodos» o «Sin período asignado», no bajo el período actual.

No se ejecutó este mantenimiento en el servidor al preparar el controlador.
Mantenga el token privado y deshabilite o proteja la ruta temporal al terminar.
