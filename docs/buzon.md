# Buzón: opiniones y encuestas por proyecto

El módulo está disponible en `/buzon` y desde «Buzón del proyecto» en el detalle de cada proyecto. Identifica el indicador `GCLCO/SCA13E/CIN3 — Uso de buzón (AAP.03)` sin necesitar su asignación a un sector para poder utilizarlo.

Cada formulario se guarda exclusivamente en `buzon_entries`. No crea filas en `reports`, `beneficiaries` ni copias de indicadores asociados. Los formularios no son personas únicas y no se agregan a los informes de beneficiarios, generales ni por indicadores existentes. Los registros históricos de otros módulos no se migran ni se modifican.

## Acceso y confidencialidad

- El único permiso del módulo es **Buzon**, asignado inicialmente al rol `admin`. Se puede conceder a otros roles o usuarios desde la configuración existente.
- Todas las rutas, la creación, la lectura, la edición y los enlaces requieren ese permiso; ser administrador no sustituye el permiso si se retira.
- Los administradores autorizados consultan todos los proyectos. Los demás usuarios autorizados consultan únicamente los proyectos asignados a su usuario. Un equipo de trabajo o el acceso geográfico nacional no concede acceso adicional al Buzón.
- La bitácora general registra la acción, pero nunca copia el formulario, nombres, teléfonos, comentarios o consultas del Buzón. No se generan notificaciones con su contenido.
- Las respuestas del módulo llevan `Cache-Control: no-store, private`. La PWA existente no almacena estas páginas ni datos del Buzón para uso sin conexión.
- La edición usa una versión para impedir que dos usuarios sobrescriban silenciosamente sus cambios. No se incluye eliminación de formularios en esta primera versión. No se permite eliminar un proyecto con entradas del Buzón; puede desactivarse para conservar el historial.

## Registro y consulta

Campos obligatorios: proyecto activo, lugar, fecha de la opinión (no futura), valoración y tipo de retroalimentación. El lugar es texto propio de la planilla, que puede corresponder a un centro o a una comunidad; no exige un registro de beneficiarios ni un lugar del catálogo.

Nombre, teléfono, edad, género, discapacidad, afrodescendencia, comentario y propuesta de mejora son opcionales, para no forzar datos personales cuando la opinión es anónima o se desconoce esa información. Se pueden indicar varias discapacidades, pero no combinar «Ninguna» con otra.

La valoración admite «Muy buena», «Ni buena ni mala» o «Mala». La clasificación del personal admite una de «Sugerencia», «Comentario» o «Queja» por formulario.

La consulta permite uno o varios proyectos, fecha de opinión desde/hasta, valoración y tipo de retroalimentación, combinados. Las tarjetas resumen cuentan todos los formularios filtrados, no solo la página visible. Los proyectos inactivos conservan sus consultas y permiten corregir entradas ya existentes, pero no crear nuevas.

## Instalación

Subir los archivos del nuevo módulo, modelos, controladores, solicitudes, middleware, vistas, CSS/JS y las versiones actualizadas de `routes/web.php`, `resources/views/layouts/app.blade.php`, `resources/views/proyectos/show.blade.php`, `ProyectoController`, `PermissionController`, `Proyecto` y `RecordAuditLog`.

Aplicar únicamente la nueva migración antes de abrir el módulo:

```sh
php artisan migrate --path=database/migrations/2026_10_23_120000_create_buzon_entries_table.php --force
php artisan optimize:clear
```

No requiere seeders ni importaciones, no cambia el catálogo de indicadores y no asigna registros a personas. No usar el modo anterior `only=lugares-proyecto` para instalar el Buzón: ese modo no ejecuta esta nueva migración.

Para instalarlo mediante el controlador temporal, utilizar `only=actualizacion-buzon` después de subir todos los archivos. Este modo también comprueba los cambios de Cédula en el Excel de beneficiarios. La lista completa y los pasos están en [la guía de despliegue del 26 de octubre](despliegue-sia-2026-10-26.md).

Las pruebas usan exclusivamente SQLite en memoria. No se guardan formularios de prueba en la base local de usuarios ni en el servidor.
