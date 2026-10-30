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

## Dictado de comentarios y mejoras

Los formularios de creación y edición ofrecen **Dictar datos** al inicio, **Dictar comentario** para comentarios, sugerencias o quejas y **Dictar mejora** para «¿Qué podemos hacer para mejorar?». La consulta de solo lectura no activa ni muestra estos controles.

Con Dictar datos se pueden completar varios campos en una sola frase, por ejemplo: «lugar Comunidad San José, nombre María González, edad 34 años, género femenino, teléfono 0414 123 4567, discapacidad ninguna, afrodescendiente no». Los campos se completan al finalizar el dictado; también se puede corregir la frase en «Ver o corregir la frase de datos» y pulsar «Aplicar datos de la frase». Esto último no requiere micrófono.

Solo se completan campos reconocidos; los demás conservan su valor. **Proyecto y fecha están excluidos del dictado**, incluso si se mencionan en la frase o se pulsa Aplicar datos: conservan siempre su selección manual. El analizador no devuelve estos campos y la aplicación de datos solo acepta una lista explícita de campos permitidos. Los teléfonos admiten cifras o dígitos individuales dictados, conservando el cero inicial. Los datos no reconocidos se dejan para revisión manual.

También se pueden indicar explícitamente discapacidad, afrodescendencia, valoración («opinión muy buena», «opinión ni buena ni mala», «opinión mala») y tipo («tipo sugerencia», «tipo comentario», «tipo queja»). No se deducen datos personales, valoraciones ni clasificaciones del contenido de las quejas; los botones de texto libre no interpretan campos.

El diseño muestra una cabecera compacta azul claro con «Dictado rápido» a la izquierda y el botón a la derecha, como en beneficiarios, seguida del ejemplo fuera de la cabecera. La transcripción y la ayuda están plegadas; al pulsar Dictar datos se abre la transcripción. «Limpiar dictado» elimina solo la frase, no los campos del formulario.

El dictado es opcional: se inicia únicamente al pulsar el botón del campo elegido, sin casilla de consentimiento adicional. Se mantiene el permiso de micrófono del navegador. Se usa `SpeechRecognition` o `webkitSpeechRecognition`, en español de Venezuela (`es-VE`), cuando el navegador lo ofrece y la página está en un contexto seguro (HTTPS o localhost). La compatibilidad depende del navegador y de su servicio; la escritura manual siempre permanece disponible.

La ayuda informa de que el navegador puede procesar audio mediante un servicio externo de reconocimiento. SIA no graba ni almacena audio, no guarda transcripciones en almacenamiento local ni añade servicios de reconocimiento propios. Referencia: [SpeechRecognition, MDN](https://developer.mozilla.org/en-US/docs/Web/API/SpeechRecognition).

En comentarios y mejoras, solo los resultados definitivos se añaden al final del campo; el texto previo y las correcciones manuales no se borran. Los resultados provisionales aparecen en una vista previa. Se puede detener y retomar el dictado o cambiar de campo una vez finalizado. No se interpreta lo hablado como comandos: decir «guardar» no envía el formulario.

Si se alcanza el límite de 10.000 caracteres, la última frase no se añade ni se trunca: permanece visible para copiarla y revisarla. Al guardar durante un dictado se detiene la captura y se pide revisar el texto y pulsar Guardar nuevamente. Al ocultar la página o salir, se cancela la captura y se ignoran los resultados tardíos. No se reinicia el micrófono automáticamente.

Para desplegar esta mejora, subir `resources/views/buzon/form.blade.php`, `public/css/buzon.css` y los nuevos `public/js/buzon-voice-data.js` y `public/js/buzon-dictation.js`, y limpiar la caché de vistas. No requiere migraciones, cambios de permisos ni modificación del formulario de beneficiarios. El contenido solo se guarda mediante el envío manual habitual del Buzón.

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
