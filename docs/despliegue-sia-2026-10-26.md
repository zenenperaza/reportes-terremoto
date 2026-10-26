# Despliegue SIA — 26 de octubre de 2026

Incluye el módulo Buzón, el permiso `Buzon`, la protección de sus datos y la columna Cédula en el Excel de Informe de beneficiarios únicamente para administradores.

## Antes de subir

1. Respaldar la base de datos y los archivos actuales del servidor.
2. Si se necesita bloquear SIA durante la subida, usar Mantenimiento y su aviso anticipado. El controlador temporal no activa, cancela ni modifica bloqueos o avisos.
3. Subir todos los archivos de ejecución indicados abajo, incluidos los archivos nuevos sin seguimiento de Git. La comprobación detecta archivos ausentes, no versiones antiguas.
4. No subir ni importar la base local, los SQL de `docs/bdd`, `.env`, credenciales, `storage` local ni cachés compiladas de `bootstrap/cache`.

## Archivos de ejecución

Rutas relativas al proyecto Laravel. Los archivos de `public/` van en la raíz pública correspondiente del hosting.

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/BuzonController.php
app/Http/Controllers/BeneficiaryReportController.php
app/Http/Controllers/PermissionController.php
app/Http/Controllers/ProyectoController.php
app/Http/Middleware/PrivateBuzonResponse.php
app/Http/Middleware/RecordAuditLog.php
app/Http/Requests/SaveBuzonEntryRequest.php
app/Models/BuzonEntry.php
app/Models/Proyecto.php
database/migrations/2026_10_23_120000_create_buzon_entries_table.php
public/css/buzon.css
public/js/buzon.js
resources/views/buzon/form.blade.php
resources/views/buzon/index.blade.php
resources/views/layouts/app.blade.php
resources/views/proyectos/show.blade.php
resources/views/beneficiaries/summary.blade.php
routes/web.php
routes/maintenance.php
```

Las pruebas y los documentos no son necesarios para ejecutar SIA en producción. No cambiaron las dependencias de Composer; PhpSpreadsheet debe seguir instalado para exportar Excel.

Este modo supone que los despliegues anteriores ya están instalados. No ejecuta las migraciones antiguas de lugares, indicadores, presencia o notificaciones.

## Ejecutar después de completar la subida

Abrir únicamente este modo, sustituyendo `TU_TOKEN` por el valor privado de `SERVER_MAINTENANCE_TOKEN` configurado en el servidor:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-buzon&token=TU_TOKEN
```

No abrir la ruta sin `only`. El modo general antiguo contiene operaciones ajenas a esta actualización. Los modos `lugares-proyecto` y `actualizacion-informes` no instalan el Buzón.

El controlador comprueba los archivos y PhpSpreadsheet antes de ejecutar comandos. Luego ejecuta exclusivamente:

```text
optimize:clear
cache:clear
migrate --path=database/migrations/2026_10_23_120000_create_buzon_entries_table.php --force
config:cache
route:cache
view:cache
limpieza de caché de permisos
```

No combinar con `only_cache`, `import_excel`, `apply` o `decisions`, ni siquiera con valor `0`. Si un comando falla, el proceso se detiene; los comandos anteriores no se revierten automáticamente. Corregir el error antes de repetir.

La migración crea exclusivamente `buzon_entries` y el permiso `Buzon`, asignado inicialmente al rol `admin`. No crea formularios, beneficiarios ni copias de indicadores. No modifica registros, catálogos, parroquias, asignaciones de proyectos, períodos ni cierres. No ejecuta seeders ni importaciones.

Al repetir, Laravel omite la migración ya aplicada: conserva las entradas del Buzón y los permisos concedidos o retirados después de su instalación.

## Comprobaciones al finalizar

- Todos los comandos deben terminar con código `0` y la respuesta debe finalizar con `PERMISSION CACHE: caché de roles y permisos limpiada correctamente.`
- Recargar SIA con **Ctrl + F5**.
- Verificar `/buzon` y el enlace «Buzón del proyecto». El permiso `Buzon` es obligatorio para todas sus rutas. Los administradores autorizados ven todos los proyectos; los demás usuarios autorizados, solo sus proyectos asignados.
- Conceder `Buzon` a otros usuarios o roles solo cuando corresponda. Los formularios no se suman a los informes de beneficiarios y no se copia su contenido en la bitácora general.
- En `/informe-beneficiarios`, el Excel del administrador debe incluir Cédula al final, después de Período. Conserva ceros iniciales y se genera con los filtros actuales. Los demás usuarios no reciben ni la columna ni sus valores, aunque puedan exportar Excel.
- No es necesario guardar formularios ni beneficiarios de prueba en producción. Habilitar SIA manualmente si se había bloqueado para la subida.

El controlador quedó preparado únicamente en local. No se ejecutó en el servidor ni se hizo `git add`, commit o push durante esta preparación.
