# Despliegue pendiente: listados del Buzón y aviso de cierre de período

## Alcance revisado en Git

Los cambios sin commit incluyen el formulario limpio después de guardar una opinión, los listados persistentes del Buzón (consultar, continuar y modificar opiniones según permisos) y el aviso de cierre de período al completar el inicio de sesión. También hay pruebas y documentación, que no necesitan publicarse para ejecutar SIA.

Se prepara un modo nuevo del controlador temporal:

```text
only=actualizacion-buzon-listados
```

Este modo presupone que el servidor ya tiene el Buzón, sus cuatro permisos por acción, los grupos/edición cruzada y el sistema de períodos. **No reinstala esas actualizaciones ni concede permisos.**

## Antes de ejecutar

1. Conservar una copia de seguridad verificada de la base de datos y del código publicado.
2. Activar el bloqueo desde el mantenimiento de SIA para evitar cargas mientras se cambia el esquema. El controlador temporal no lo activa ni lo retira automáticamente.
3. Subir todos los archivos de ejecución que se indican a continuación, incluidos los **archivos nuevos sin seguimiento en Git**. No basta con subir los archivos que ya existían.

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/BuzonController.php
app/Http/Controllers/ProyectoController.php
app/Http/Requests/SaveBuzonEntryRequest.php
app/Models/BuzonList.php
app/Models/BuzonEntry.php
app/Models/Proyecto.php
app/Providers/AppServiceProvider.php
app/Support/PeriodClosingNotice.php
database/migrations/2026_11_11_120000_create_buzon_lists.php
resources/views/buzon/form.blade.php
resources/views/buzon/index.blade.php
resources/views/buzon/lists/index.blade.php
resources/views/buzon/lists/show.blade.php
resources/views/buzon/lists/partials/entries.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/partials/period-closing-notice.blade.php
public/css/buzon.css
public/css/period-closing-notice.css
public/js/period-closing-notice.js
routes/web.php
```

El controlador comprueba además dependencias que ya deberían estar publicadas: `routes/maintenance.php`, User y UserGroup, ReportPeriod, SystemSetting, ReportingPeriod, los middleware del Buzón/bitácora, el botón de eliminación y los JavaScript de Buzón/dictado. Si falta alguna, hay que subirla antes de continuar.

La comprobación verifica presencia de archivos, disponibilidad de métodos y algunas referencias clave en provider, vistas, rutas y solicitudes para detectar una subida parcial evidente. No sustituye comprobar que se subió la versión actual de todos los archivos. Si el código en disco es correcto pero PHP continúa cargando métodos antiguos, revisar la caché OPcache del alojamiento.

## Ejecución en el servidor

Después de completar la subida, abrir mediante HTTPS y sustituir `TU_TOKEN` por el valor existente de `SERVER_MAINTENANCE_TOKEN`, codificado para URL si contiene caracteres especiales:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-buzon-listados&token=TU_TOKEN
```

**No omitir `only` ni combinar este modo con `only_cache`, `import_excel`, `apply` o `decisions`.** Incluso enviadas vacías o como `0`, esas opciones son rechazadas. No publicar ni compartir el enlace con el token real.

Tras completar las comprobaciones, el orden de comandos es:

1. `optimize:clear`
2. `cache:clear`
3. `migrate --path=database/migrations/2026_11_11_120000_create_buzon_lists.php --force`
4. `config:cache`
5. `route:cache`
6. `view:cache`
7. Limpieza de la caché de permisos, sin cambiar sus asignaciones.

La migración crea `buzon_lists` y agrega `buzon_list_id` opcional en `buzon_entries`. No crea listados ni opiniones durante el mantenimiento y no agrupa las opiniones antiguas. Si Laravel ya tiene registrada la migración, la omite: repetir el modo no borra listados ni vuelve a conceder permisos.

Si falta un archivo o se detecta una referencia antigua, se detiene **antes de ejecutar comandos**. Si falla un comando, no se ejecutan los pasos siguientes. No eliminar tablas ni forzar reparaciones para reintentar un fallo parcial de esquema: conservar el bloqueo y revisar la salida antes de continuar.

## Qué no hace

- No ejecuta todas las migraciones pendientes, seeders, importaciones o correcciones de parroquias.
- No crea, modifica, elimina, une ni reclasifica registros, beneficiarios u opiniones existentes.
- No cambia permisos, proyectos asignados, grupos, períodos ni cierres.
- No activa, cancela ni retira el bloqueo o los avisos programados de mantenimiento.
- No cierra períodos por calendario ni envía datos al donante. El aviso de cierre es informativo.

## Comprobación final

1. Confirmar que todos los comandos terminen con código `0` y aparezca la confirmación de caché de permisos.
2. Recargar SIA con **Ctrl + F5**.
3. Probar una carga autorizada del Buzón: después de guardar, el formulario debe quedar limpio y la opinión aparecer abajo, dentro del mismo listado.
4. Abrir **Consultar listados**, retomarlo y revisar edición/eliminación individual con los permisos y la edición cruzada correspondientes.
5. Comprobar que las opiniones antiguas siguen consultables sin estar agrupadas automáticamente.
6. El aviso de cierre solo aparece en un nuevo acceso dentro de la ventana correspondiente al período abierto: en noviembre de 2026, del **25 al 30**. No cambiar el reloj ni los períodos de producción solo para forzarlo.
7. Retirar el bloqueo manualmente cuando se haya comprobado el funcionamiento.

La migración de listados se aplicó previamente **solo en local**. Preparar este modo no lo ejecuta en el servidor ni hace `git add`, commit o push.

La revisión ampliada anterior detectó una prueba antigua de opciones de filtros de períodos que falla también con el aviso desactivado. Este despliegue no altera esos filtros ni corrige esa prueba. Las pruebas del nuevo modo simulan Artisan y el token; no ejecutan mantenimiento real.
