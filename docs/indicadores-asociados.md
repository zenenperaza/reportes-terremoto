# Indicadores asociados

## Configuración y uso

En **Proyectos → Sectores → Indicadores**, la columna **Indicadores asociados** permite gestionar las relaciones de cada indicador del proyecto.

- Solo se pueden asociar otros indicadores asignados a sectores del mismo proyecto.
- La relación es dirigida: asociar B a A no asocia A a B automáticamente.
- Al crear un registro, se ofrecen los asociados activos del principal, sin seleccionarlos automáticamente.
- Los asociados no aparecen como tarjetas principales en nuevos registros. Solo se muestran después de elegir su principal; volver a pulsarlo lo desmarca y elimina todas las selecciones asociadas. Al cambiar de principal también se limpian. Un registro asociado ya guardado conserva su indicador al abrirlo para edición.
- Cada asociado seleccionado genera un registro independiente y una copia de cada beneficiario. Principal + dos asociados = tres registros, cada uno con su propia fila de beneficiario.
- Los siguientes beneficiarios de la misma sesión y selección se agregan a esos mismos registros. Cambiar la selección inicia un nuevo grupo.
- No se siguen asociaciones recursivamente, aunque el catálogo contenga ciclos.
- Se preservan período, fecha, autor, ubicación y datos de la persona. No se copian actividades ni servicios, pues corresponden al indicador principal.
- Se validan las edades de todos los indicadores seleccionados. El guardado de las filas es transaccional: si alguno falla, no se guardan filas parciales.
- Las copias cuentan como atenciones independientes en los informes. Las reglas existentes de cada informe para personas únicas siguen aplicándose.
- Editar o eliminar una persona ya guardada no modifica automáticamente sus copias. Cambiar la configuración tampoco altera registros históricos.
- Si una copia fue eliminada, revisada o cambió sus encabezados, no se añade parcialmente un nuevo beneficiario: el sistema solicita iniciar un nuevo registro.

## Despliegue

Primero haga un respaldo y suba todos los archivos modificados y nuevos de esta funcionalidad, incluido `TemporaryMaintenanceController.php`, la migración, el trait `ValidatesAssociatedIndicators.php`, la vista `asociados.blade.php` y `public/js/associated-indicators.js`. Los archivos que ya existían también deben subirse con su versión actual.

En el servidor, abra la ruta (sustituya `TU_TOKEN` por su `SERVER_MAINTENANCE_TOKEN`, sin compartirlo):

```text
/ejecutar-migraciones-temp?only=indicadores-asociados&token=TU_TOKEN
```

Este modo verifica la presencia de los archivos necesarios y ejecuta, en orden: `optimize:clear`, `cache:clear`, solo la migración de asociados, `config:cache`, `route:cache` y `view:cache`. También limpia la caché de permisos. Se detiene ante un fallo; todos los comandos deben finalizar con código 0.

No use la ruta sin `only=indicadores-asociados`, porque el modo general incluye otros procesos. No combine este modo con opciones de importación. Si Laravel informa `Nothing to migrate`, la migración ya estaba aplicada: no se reinician asociaciones ni copias existentes. Este despliegue no transfiere las asociaciones configuradas en la base local al servidor; en el servidor se configuran desde **Indicadores asociados → Gestionar**.

Alternativa mediante terminal, ejecutando únicamente esta migración antes de usar los nuevos formularios:

```sh
php artisan migrate --path=database/migrations/2026_09_30_120000_create_associated_indicators_tables.php --force
php artisan view:clear
php artisan route:cache
```

La migración crea `indicador_proyecto_asociados` y `report_indicator_copies`. No modifica los registros/beneficiarios existentes, no ejecuta seeders y no configura asociaciones automáticamente. Una vez aplicada, Laravel la omite al repetir el comando.

El modo anterior `only=periodos` de TemporaryMaintenanceController **no ejecuta esta nueva migración**. No es necesario volver a importar SQL de períodos ni beneficiarios para este cambio.

## Verificación

```sh
php artisan test --compact --filter="AssociatedIndicatorsTest|ReportPeriodTest|BeneficiaryAttentionEditingTest|ProjectIndicatorSchemaTest"
node --test tests/JavaScript/associated-indicators.test.cjs
```

Las pruebas de PHP usan SQLite en memoria, no la base local de trabajo.
