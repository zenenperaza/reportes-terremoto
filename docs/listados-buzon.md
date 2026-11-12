# Listados persistentes de opiniones del Buzón

## Uso

- Abrir **Registrar opinión** inicia una carga nueva. El listado se crea únicamente al guardar correctamente la primera opinión: abrir o recargar el formulario no crea filas.
- Cada listado tiene un número, proyecto, autor y fecha de creación. Sus opiniones se guardan inmediatamente en la base de datos.
- Tras guardar, el formulario queda limpio, con fecha de hoy y el proyecto del listado. Debajo aparecen las opiniones guardadas, con paginación y acciones por permiso.
- **Iniciar nuevo listado** permite separar otra carga. No combina listados anteriores, ni mueve o borra sus opiniones.
- **Consultar listados**, disponible desde Buzón, abre `/buzon/listados`. Desde **Ver listado** se pueden consultar las opiniones, editarlas, eliminarlas o pulsar **Agregar opiniones** para continuar el mismo listado.
- Los listados sobreviven al cierre de sesión y pueden retomarse posteriormente. Su identificación viaja en la URL del formulario y en un campo oculto validado; no depende de una lista de IDs almacenada en la sesión del navegador.
- Editar una opinión vinculada o eliminarla devuelve al listado. El control de versión existente sigue impidiendo sobrescribir o eliminar datos modificados por otro usuario.
- Eliminar la última opinión conserva el listado vacío para poder reutilizarlo. No se ofrece eliminación masiva ni eliminación del contenedor.
- Las opiniones históricas quedan con `buzon_list_id = NULL`, siguen consultables en **Consultar opiniones** y no se agrupan automáticamente.

## Permisos y confidencialidad

Se utilizan los cuatro permisos existentes, sin nuevas asignaciones: **ver buzon**, **crear buzon**, **editar buzon**, **eliminar buzon**. Se conservan el alcance por proyecto, autor y grupos activos, y la edición cruzada entre compañeros. Los administradores tienen alcance global únicamente con el permiso de la acción.

- Consultar listados y sus opiniones exige **ver buzon**. Los conteos incluyen solo las opiniones visibles para el usuario, incluso cuando se comparta un listado.
- Agregar exige **crear buzon**, proyecto activo asignado (salvo administrador) y ser autor del listado o compartir con él un grupo activo con edición cruzada habilitada. El proyecto del listado no puede cambiarse desde una opinión ni falsificarse al guardar.
- Un usuario con solo **crear buzon** puede seguir agregando a su propio listado, pero no ve las opiniones anteriores ni puede continuar listados de compañeros.
- Editar o eliminar sigue comprobando el autor de cada opinión, **ver buzon**, el permiso específico de la acción y, entre compañeros, edición cruzada activa.
- La autorización se vuelve a comprobar al guardar; retirar un proyecto, un permiso o la edición cruzada invalida formularios abiertos.
- No se crean beneficiarios ni registros de otros indicadores, ni se notifican contenidos privados. Las nuevas rutas conservan `no-store, private` y la exclusión de contenidos del Buzón en la bitácora general.

## Despliegue

Esta funcionalidad **requiere una nueva migración**, además de los archivos de código y vistas. La migración crea `buzon_lists` y añade una referencia opcional en `buzon_entries`. No modifica, une, reclasifica ni elimina opiniones históricas. No crea permisos ni ejecuta seeders.

Con el sistema bloqueado para la actualización, subir todos estos archivos conjuntamente:

```text
database/migrations/2026_11_11_120000_create_buzon_lists.php
app/Models/BuzonList.php
app/Models/BuzonEntry.php
app/Models/Proyecto.php
app/Http/Controllers/BuzonController.php
app/Http/Controllers/ProyectoController.php
app/Http/Requests/SaveBuzonEntryRequest.php
routes/web.php
resources/views/buzon/form.blade.php
resources/views/buzon/index.blade.php
resources/views/buzon/lists/index.blade.php
resources/views/buzon/lists/show.blade.php
resources/views/buzon/lists/partials/entries.blade.php
public/css/buzon.css
```

Aplicar únicamente la migración nueva contra la base prevista para el despliegue:

```sh
php artisan migrate --path=database/migrations/2026_11_11_120000_create_buzon_lists.php --force
```

Renovar las cachés de Laravel por el procedimiento habitual. **Limpiar cachés no sustituye esta migración**; los modos antiguos del controlador temporal no la ejecutan. Ya se preparó `only=actualizacion-buzon-listados`, que incluye esta migración y el aviso de cierre: seguir la [guía de despliegue del 12 de noviembre](despliegue-buzon-listados-aviso-2026-11-12.md). No se ha ejecutado en el servidor.

Pruebas con SQLite en memoria: `BuzonListsTest`, `BuzonTest`, `BuzonActionPermissionsTest` y `BuzonGroupAccessTest`. Se comprueban continuidad tras cerrar sesión, formulario vacío, permisos, proyectos, edición cruzada y su revocación, versión, ausencia de agrupación histórica, protección de datos y no duplicación al recargar.

La migración nueva se aplicó únicamente en MySQL local (`127.0.0.1`), después de comprobar la conexión. No se ejecutó en el servidor ni se registraron opiniones de prueba en la base local.
