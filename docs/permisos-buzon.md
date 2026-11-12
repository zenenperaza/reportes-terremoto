# Permisos por acción del Buzón

Se sustituyó la comprobación única `Buzon` por estos permisos del guard `web`:

- `ver buzon`: listado, filtros, resúmenes y consulta de formularios.
- `crear buzon`: abrir y guardar un formulario nuevo. Puede asignarse sin
  permiso de ver; al guardar se vuelve a un formulario vacío, no al detalle.
- `editar buzon`: corregir formularios existentes. Requiere también `ver buzon`.
- `eliminar buzon`: borrar un formulario. Requiere también `ver buzon`.

Los permisos se administran desde Roles. Se comprueban en las rutas y en las
solicitudes de guardado, además de ocultar botones, enlaces y opciones del menú.
El alcance combina proyectos asignados y autoría:

- Un usuario ve sus propias opiniones y las creadas por miembros de sus grupos
  activos, siempre dentro de sus proyectos asignados.
- Pertenecer al mismo proyecto no permite consultar opiniones de otro equipo.
- Para editar o eliminar una opinión de un compañero debe compartirse un grupo
  activo que tenga `allow_member_editing` (Edición cruzada) habilitado. No basta
  con que el compañero esté en otro grupo que sí la tenga activada.
- Esa opción no concede permisos por sí sola: siguen siendo necesarios
  `ver buzon` y el permiso específico de editar o eliminar. La regla aplica a
  registradores, coordinadores y roles personalizados con esos permisos.
- Las opiniones propias pueden modificarse sin activar edición cruzada, con
  los permisos correspondientes. Si no hay grupos activos solo se ven las propias.
- La condición de administrador permite alcance global, pero no sustituye los
  permisos de cada acción. Las opiniones antiguas sin autor verificable quedan
  visibles solo para administradores; no se les asigna un autor automáticamente.

La misma restricción se aplica a listados, totales, filtros, detalles y acciones
directas por URL. Los botones de editar y eliminar se ocultan por cada opinión.
Se comprueba de nuevo al guardar o borrar: una pantalla abierta no conserva
autorización si se retira la edición cruzada o la pertenencia al grupo.

Los [listados persistentes de carga](listados-buzon.md) utilizan esos mismos permisos. Consultarlos exige `ver buzon`; agregar opiniones exige `crear buzon` y ser autor del listado o compartir un grupo activo con edición cruzada. Sin permiso de ver solo se puede continuar un listado propio, sin exponer las opiniones guardadas. Los conteos se limitan a opiniones visibles y el proyecto de cada listado permanece fijo.

La eliminación pide confirmación y comprueba la versión del formulario dentro
de una transacción: no elimina una opinión modificada después de cargar la
pantalla sin que se vuelva a revisar. Es un borrado permanente. No afecta a
beneficiarios ni registros de otros indicadores. El contenido del Buzón sigue
excluido de la bitácora general y las respuestas conservan `no-store, private`.

## Migración

`database/migrations/2026_11_04_120000_add_buzon_action_permissions.php`

Crea los cuatro permisos y los asigna al rol Administrador. A los roles y usuarios
que ya tenían `Buzon` les conserva las acciones anteriores: ver, crear y editar.
No les concede eliminar automáticamente.

El permiso antiguo `Buzon` se conserva para compatibilidad con un despliegue
anterior, pero **ya no habilita acciones por sí solo**. Para nuevas asignaciones
se deben utilizar los cuatro permisos específicos.

Aplicación dirigida, después de subir todos los archivos:

```powershell
php artisan migrate --path=database/migrations/2026_11_04_120000_add_buzon_action_permissions.php --force
```

La migración solo administra permisos: no modifica ni elimina opiniones.
Se aplicó en la base local; no se ha ejecutado en producción. Actualizar las
cachés de rutas y vistas según el procedimiento de despliegue de SIA. El modo
antiguo `only=actualizacion-buzon` del controlador temporal no incluye esta
migración nueva; no es suficiente para desplegar este cambio.

Para desplegar junto con la continuación de registros ya está preparado el modo
`only=actualizacion-buzon-registros`. Consultar
[despliegue-buzon-registros-2026-11-04.md](despliegue-buzon-registros-2026-11-04.md).

## Archivos de ejecución para subir

- La migración indicada.
- `app/Http/Controllers/BuzonController.php`
- `app/Http/Controllers/PermissionController.php`
- `app/Http/Requests/SaveBuzonEntryRequest.php`
- `app/Models/BuzonEntry.php`
- `app/Models/User.php` (reutiliza las reglas de miembros de grupos activos).
- `routes/web.php` (conservando los cambios de continuación de registros).
- `resources/views/buzon/index.blade.php`
- `resources/views/buzon/form.blade.php`
- `resources/views/buzon/partials/delete-button.blade.php` (nuevo).
- `resources/views/layouts/app.blade.php`
- `resources/views/proyectos/show.blade.php`
- `public/js/buzon.js`
- `public/css/buzon.css`

Pruebas específicas:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/BuzonTest.php tests/Feature/BuzonActionPermissionsTest.php tests/Feature/BuzonGroupAccessTest.php --no-progress
node --test tests/JavaScript/buzon.test.cjs
```
