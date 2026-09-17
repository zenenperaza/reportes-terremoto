# Gestión de casos y familias

El módulo `/casos` registra expedientes individuales de personas de todas las edades. La interfaz tiene listado con filtros y paginación desde el servidor, búsqueda previa de coincidencias, navegación lateral y consulta/edición del expediente.

## Alcance disponible

- Identificación y registro: código automático, documento opcional, fecha de nacimiento o edad estimada, sexo, nacionalidad, tipo de caso, proyecto y responsable.
- Ubicación y contacto: estado, municipio, parroquia y forma segura de contacto.
- Familias: registro independiente, datos generales, integrantes repetibles, vínculo de varios casos a una familia y persona de apoyo.
- Consentimiento: pendiente, otorgado o no otorgado; fecha, fuente y autorizaciones separadas para servicios y reportes.
- Evaluación inicial: riesgo, necesidades y acciones inmediatas.
- Formularios ampliados: incidentes y presuntos responsables; riesgos de protección y sus detalles; otros datos de identidad; plan e intervenciones; acuerdos de cuidado; servicios; seguimientos; derivaciones y notas repetibles.
- Aprobaciones de evaluación, plan y cierre, editables solo por supervisores. Cierre/reapertura restringido a supervisores; el cierre requiere fecha y motivo.
- Fotos, audio y documentos privados, con autorización en cada descarga y eventos de carga/descarga.
- Navegación lateral agrupada y etapas al estilo de las capturas de Primero. La etapa refleja la información guardada, no es un botón de cambio manual.
- Historial del expediente: creación, campos modificados, cambio de responsable y consultas del detalle, edición e historial. No conserva versiones anteriores de los valores personales.

Los tipos iniciales son Protección general, Protección de la niñez y VBG. La clasificación la elige el profesional; no se deduce automáticamente de la edad o el sexo. Una edad estimada se conserva como dato al momento del registro. Los expedientes todavía no generan atenciones ni conteos de indicadores.

## Permisos

Todos se crean y asignan inicialmente al rol `admin`. Se administran en Configuración → Roles.

| Permiso | Alcance |
|---|---|
| ver casos | Acceder al módulo y a los expedientes asignados al usuario. |
| crear casos | Crear un expediente; requiere también ver casos. |
| editar casos | Editar expedientes a los que ya tiene acceso. |
| asignar casos | Elegir o cambiar el responsable al crear/editar un caso accesible. |
| supervisar casos | Consultar expedientes y familias de otros responsables, aprobar y cerrar/reabrir casos; requiere ver casos. |
| ver historial de casos | Consultar eventos de un expediente accesible. |
| gestionar casos vbg | Permitir el acceso y registro de casos VBG junto con los permisos anteriores. |

Pertenecer a un grupo de usuarios no concede acceso a expedientes ajenos. La búsqueda de coincidencias utiliza las mismas restricciones; no informa de coincidencias ocultas. Cambiar el responsable revoca el acceso del anterior salvo que tenga supervisión. Los responsables deben estar activos y tener acceso al tipo de caso.

Los datos del formulario y de las búsquedas se omiten de la bitácora general; allí se conserva la operación y su resultado. Las respuestas del módulo llevan `Cache-Control: no-store, private`. La edición comprueba una versión para evitar sobrescrituras concurrentes.

Casos y Familias usan los mismos permisos de consulta/creación/edición. Las familias pertenecen al usuario creador; supervisión permite consultarlas y editarlas con los demás permisos correspondientes. La marca «Acceso restringido» exige además `gestionar casos vbg`. Una asociación no concede acceso al resto de casos ni al registro familiar. Las listas de casos asociados se filtran por los permisos de cada caso, también para supervisores sin permiso VBG. Los integrantes familiares se registran manualmente: no se copian datos sensibles automáticamente desde los casos.

`form_data` y los detalles/integrantes familiares se guardan cifrados con la clave de la aplicación. **Conserve `APP_KEY` de forma segura al hacer respaldos/restauraciones**. Los campos básicos preexistentes de identificación siguen usando su almacenamiento original. Los archivos se guardan en `storage/app/private/case-attachments` y no se publica un enlace de almacenamiento. El respaldo SQL no incluye esos archivos; deben incluirse en el respaldo privado del servidor. No se incorpora antivirus: la validación de tipo/tamaño no sustituye un análisis de malware.

## Activación

Las migraciones deben ejecutarse en este orden. La primera crea `case_records`, `case_events` y permisos; la segunda agrega los formularios ampliados, familias, historial familiar y adjuntos sin borrar expedientes existentes. En consola:

```sh
php artisan migrate --path=database/migrations/2026_09_16_150000_create_case_management_tables.php
php artisan migrate --path=database/migrations/2026_09_16_170000_extend_case_forms_and_create_families.php
```

Ambas están incluidas en `TemporaryMaintenanceController`, dentro del flujo existente `/ejecutar-migraciones-temp` protegido por `SERVER_MAINTENANCE_TOKEN`, para el despliegue sin consola. Ese flujo ejecuta además sus tareas de mantenimiento anteriores. La activación en un servidor requiere subir el código y ejecutar el mantenimiento autorizado allí. No se publican ni cambian claves de mantenimiento.

Los formularios aceptan hasta 50 filas por sección y 100 archivos por caso, de hasta 15 MB cada uno. Configure `upload_max_filesize`/`post_max_size` según el alojamiento. Para expedientes con muchas filas, revise `max_input_vars` (por ejemplo, 10000) en el editor PHP de cPanel. Un marcador al final del formulario permite rechazar peticiones truncadas en vez de guardar datos incompletos.

## Referencia y diferencias con Primero

Se consultaron en modo lectura `D:\Onedrive\www\primero\db\configuration\forms\case`, `forms\family` y `configurations\venezuela\translations_es.rb`. La implementación es propia para Laravel y toma la organización y conceptos de esos formularios y de las capturas; no ejecuta los seeds Ruby, importa expedientes ni modifica Primero. Los seeds locales son definiciones predeterminadas: la instancia instalada puede tener personalizaciones adicionales en su base de datos que no estén en esos archivos.

El catálogo de esta adaptación está en `config/case-forms.php`. Conserva campos básicos de la primera versión para no perder datos. Los nombres completos y la geografía continúan usando el modelo existente; se agregan datos de identidad complementarios. Algunos catálogos externos de Primero (agencias, idiomas, países, proveedores) se representan como texto y los catálogos de riesgos son iniciales, no una copia completa de los lookups configurados en producción. Las etiquetas se adaptan a personas de todas las edades.

Las derivaciones documentan operaciones: **no envían datos a Primero ni a agencias externas**. Las asignaciones internas sí cambian el responsable y sus permisos de acceso al caso. «Casos vinculados» muestra expedientes de la misma familia autorizados; no existe todavía un vínculo independiente entre casos de familias distintas. «Resumen» conserva consentimiento y deseos de localización/reunificación, pero no implementa el motor de concordancias de Primero. No se incluyen solicitudes de localización, conexión sin internet, exportación de expedientes ni generación automática de atenciones/indicadores. El historial registra acciones y campos afectados, no una copia recuperable de los valores anteriores.

## Verificación

`php artisan test --filter='CaseManagementTest|CaseFamiliesAndFormsTest'` comprueba edades, permisos por expediente/familia y VBG, búsqueda acotada, reasignación, consentimiento, ubicación, historial, paginación, conflicto de ediciones, formularios repetibles, cifrado y adjuntos. Incluye envío de controles extraídos del HTML real de creación/edición y validación de todos los campos del catálogo. La comprobación visual en navegador queda pendiente cuando no hay navegador conectado.
