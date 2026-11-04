# Despliegue: permisos del Buzón y continuación de registros

El modo `only=actualizacion-buzon-registros` de `TemporaryMaintenanceController`
prepara los dos cambios pendientes: permisos por acción del Buzón y la opción
**Continuar agregando beneficiarios**.

Este procedimiento presupone que el servidor ya tiene instaladas las
actualizaciones anteriores, incluidas la tabla del Buzón, nacionalidad y los
campos del cuidador. No instala de nuevo esos módulos.

## Subir antes de ejecutar

Subir todos los archivos de ejecución indicados en
[permisos-buzon.md](permisos-buzon.md) y
[continuar-registros-beneficiarios.md](continuar-registros-beneficiarios.md),
además de:

- `app/Http/Controllers/TemporaryMaintenanceController.php` actualizado.
- `database/migrations/2026_11_04_120000_add_buzon_action_permissions.php`.

No omitir los archivos nuevos:

- `app/Http/Requests/ContinueReportBeneficiaryRequest.php`.
- `app/Services/ReportContinuation.php`.
- `resources/views/buzon/partials/delete-button.blade.php`.

Subir también `app/Models/User.php` actualizado: el Buzón reutiliza los miembros
visibles y editables de los grupos existentes. No se crean ni cambian grupos,
miembros o marcas de edición cruzada durante el despliegue.

Incluye también los datos del cuidador **opcionales** para menores de 18 años,
tanto al registrar como al continuar o editar. Subir actualizados:

- `app/Http/Requests/Concerns/ValidatesBeneficiaryCaregiver.php`.
- `app/Http/Requests/StoreBeneficiaryEntryRequest.php`.
- `app/Http/Requests/StoreReportRequest.php`.
- `app/Http/Requests/UpdateBeneficiaryRequest.php`.
- `resources/views/reports/create.blade.php` y `resources/views/reports/show.blade.php`.

Este ajuste no añade otra migración ni borra datos de cuidadores ya registrados.

El controlador comprueba la presencia de estos archivos y de sus dependencias
antes de ejecutar ningún comando. Esa comprobación no compara versiones:
los archivos que ya existían también deben subirse actualizados. Adicionalmente
se comprueba que `User.php` tenga disponibles los métodos públicos de autores
visibles y editables; si todavía es la versión antigua, se detiene el proceso.
Los archivos
de pruebas y documentación no se necesitan para ejecutar SIA en producción.

## Ejecutar

Si se va a bloquear SIA, utilizar previamente la opción de mantenimiento del
sistema y esperar a que los usuarios terminen de guardar. Este controlador no
activa ni retira el bloqueo y no cancela avisos programados.

Después de subir todos los archivos, abrir únicamente esta dirección,
sustituyendo `TU_TOKEN` por el valor ya configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-buzon-registros&token=TU_TOKEN
```

No abrir el endpoint sin `only`: el modo antiguo por defecto tiene un alcance
mucho mayor. No combinar con `only_cache`, `import_excel`, `apply` ni `decisions`.
El modo antiguo `only=actualizacion-buzon` tampoco instala los nuevos permisos.

Orden de ejecución:

1. `optimize:clear`.
2. `cache:clear`.
3. Solo la migración `2026_11_04_120000_add_buzon_action_permissions.php`, con `--force`.
4. `config:cache`.
5. `route:cache`.
6. `view:cache`.
7. Reinicio de la caché de permisos.

La migración crea los cuatro permisos del Buzón y los concede al rol
Administrador. Conserva ver, crear y editar para quienes tenían el permiso
anterior, sin concederles eliminar automáticamente. Si la migración ya figura
como aplicada, Laravel la omite: repetir el modo no restablece permisos retirados
posteriormente.

No se ejecutan seeders ni importaciones. No se crean, unen, eliminan ni
reclasifican registros, beneficiarios u opiniones durante el mantenimiento. No
se modifican parroquias, períodos, cierres ni asignaciones de proyectos.

## Comprobación final

Todos los comandos deben mostrar código `0` y terminar con `PERMISSION CACHE`.
Si falta un archivo o falla un comando, el proceso se detiene; revisar el mensaje
antes de continuar. No compartir el token ni capturas donde sea visible.

Recargar con Ctrl + F5 y comprobar:

- Los cuatro permisos nuevos aparecen en Roles.
- Cada usuario solo ve opiniones propias y de sus grupos activos, en sus proyectos.
- Editar o eliminar opiniones de compañeros requiere el permiso de la acción y
  un grupo compartido activo con Edición cruzada habilitada. Los administradores
  conservan el alcance global, con sus permisos correspondientes.
- La opción de continuar aparece en Registros y en el detalle de un listado
  modificable. Se recuperan su período y las personas guardadas.
- Una persona nueva se agrega al mismo registro. No es necesario crear ni
  eliminar registros de prueba en producción para verificar el despliegue.

Retirar manualmente el mantenimiento cuando se hayan terminado las revisiones.
Este procedimiento está preparado y probado localmente; no se ha ejecutado en
el servidor desde esta sesión.
