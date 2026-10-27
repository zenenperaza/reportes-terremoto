# Indicadores Resumen

La sección está en **Configuración → Proyectos → Indicadores Resumen**, en `/configuracion/indicadores-resumen`. Su administración utiliza el acceso administrativo existente del sistema.

1. Crear el resumen eligiendo obligatoriamente su proyecto, código, nombre, descripción opcional y estado.
2. Abrir **Asignar indicadores** y elegir el resumen. El proyecto se determina por el resumen, no se elige nuevamente.
3. Marcar sus indicadores y guardar. Solo aparecen y se admiten indicadores asignados a ese proyecto.

Cada resumen pertenece a un único proyecto. Cada miembro es una asignación concreta de `indicador_proyecto`, no el código genérico del catálogo. Por ejemplo, incluir el indicador X del proyecto A no incluye ese mismo indicador del proyecto B. Un indicador puede pertenecer a varios resúmenes del mismo proyecto. Guardar las asignaciones reemplaza la selección de ese resumen y no modifica otros resúmenes.

Si un resumen ya tiene miembros, cambiarlo de proyecto requiere confirmar explícitamente que se retiren sus asignaciones anteriores. Esto solo elimina los vínculos de ese resumen, nunca indicadores originales, registros ni beneficiarios. Después se asignan indicadores del nuevo proyecto. El proyecto no puede eliminarse mientras tenga resúmenes vinculados.

## Informes y exportaciones

El filtro **Indicadores Resumen** permite elegir uno o varios en:

- Registros de actividades: listado, consolidado y descargas existentes.
- Informe de beneficiarios y su Excel.
- Informes generales y por indicadores, incluidos sus Excel.
- Informes por Servicios: sus cuatro vistas y sus Excel.

Las opciones muestran el código del proyecto y del resumen. Es posible consultar varios resúmenes, incluso de distintos proyectos; cada uno aporta solo los indicadores de su propio proyecto. Los resúmenes seleccionados se combinan como una unión. Se usan subconsultas de pertenencia, no joins que multipliquen filas: un mismo registro no se duplica por pertenecer a varios resúmenes. Esto no convierte los registros en personas únicas ni cambia las reglas actuales de recurrencia o conteo de beneficiarios.

Los demás filtros se aplican simultáneamente: período, fechas, reportado, estado, indicador individual, servicio, etc. Si se elige además un indicador individual incompatible con el resumen, el resultado es vacío. Sin seleccionar resúmenes, el comportamiento anterior no cambia. Seleccionar un resumen activo sin miembros devuelve cero registros, nunca todos.

Los informes de beneficiarios conservan la exclusión de los indicadores marcados como excluidos. Los informes generales, por indicadores y de servicios mantienen sus reglas propias. Los usuarios siguen viendo únicamente registros autorizados; seleccionar resúmenes no amplía permisos. Los no administradores solo reciben opciones de resumen vinculadas a indicadores de su proyecto con registros que ya pueden consultar. Las consultas también comprueban el proyecto del resumen, del indicador asignado y del registro, incluso si existe algún vínculo antiguo incorrecto.

Un resumen inactivo conserva sus asignaciones administrativas, pero no se ofrece como filtro. Los indicadores de proyecto inactivos pueden seguir vinculados para consultar registros históricos. No se permite eliminar un resumen con miembros: deben retirarse sus asignaciones o desactivarse.

## Registro de actividades intacto

No se modifica `/reportes/nuevo`, su vista, las solicitudes de guardado ni la selección de indicadores principales/asociados. Crear o configurar resúmenes no crea registros, beneficiarios, indicadores originales ni copias asociadas, ni modifica datos históricos. El Buzón continúa separado de los registros de actividades.

## Instalación

Después de subir los archivos nuevos y modificados, ejecutar únicamente:

```sh
php artisan migrate --path=database/migrations/2026_10_26_140000_create_summary_indicators_tables.php --force
php artisan migrate --path=database/migrations/2026_10_26_150000_scope_summary_indicators_to_projects.php --force
php artisan view:clear
```

La primera migración crea `summary_indicators` y `summary_indicator_assignments`, sin asignaciones automáticas, seeders ni importaciones. La segunda añade la referencia al proyecto sin deducirla de pruebas ni modificar asignaciones existentes. Los resúmenes anteriores quedan con **proyecto pendiente** y no aparecen como filtros hasta que un administrador confirme su proyecto en Editar. Si todos sus miembros pertenecen al proyecto elegido explícitamente, se conservan; de lo contrario, se solicita confirmación para reiniciar únicamente esos vínculos.

### Desde el controlador temporal

Como alternativa a los comandos anteriores, después de respaldar la base del servidor y subir **todos los archivos nuevos y modificados**, incluido `TemporaryMaintenanceController.php` y ambas migraciones, abrir:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=indicadores-resumen&token=TU_TOKEN
```

Sustituir `TU_TOKEN` por el `SERVER_MAINTENANCE_TOKEN` ya configurado en el `.env` del servidor. No publicar el token ni subir el `.env` local. No usar la ruta sin `only=indicadores-resumen`: el modo general antiguo también ejecuta seeders.

El modo comprueba que existan los archivos necesarios y PhpSpreadsheet antes de ejecutar comandos. Limpia cachés, aplica únicamente las dos migraciones indicadas, en orden, y reconstruye las cachés de configuración, rutas y vistas. Se detiene ante cualquier error; las migraciones ya aplicadas se omiten al repetirlo. No crea resúmenes de prueba, no asigna proyectos automáticamente ni cambia registros, beneficiarios, Buzón, permisos, períodos, bloqueos o avisos programados. El modo anterior `actualizacion-buzon` se conserva, pero no instala las tablas de resúmenes.

Si hay mantenimiento activado, se mantiene hasta desactivarlo manualmente. Al terminar, recargar con Ctrl + F5 y comprobar Configuración → Proyectos → Indicadores Resumen. El controlador se prepara localmente: no ejecuta por sí solo ningún cambio en el servidor. Después del despliegue, retirar o deshabilitar el acceso temporal según el procedimiento habitual.

Las pruebas utilizan SQLite en memoria. No se deben guardar registros de ejemplo en la base real para verificar la funcionalidad.
