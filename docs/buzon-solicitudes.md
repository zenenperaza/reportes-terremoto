# Buzón: tipo Solicitud y retroalimentación primero

## Comportamiento

Los formularios internos (nuevo, continuar listado, editar y consultar) y públicos presentan primero el tipo de retroalimentación como grupo de radios: **Sugerencia, Comentario, Queja, Solicitud**. Solo se puede elegir uno.

Nombre y Teléfono están **siempre visibles**, tanto en el formulario interno como en el público. Al elegir **Solicitud** ambos pasan a ser obligatorios. La validación también se aplica en el servidor al crear y editar, incluso sin JavaScript. Los espacios en blanco no son datos válidos. Se conservan los límites existentes de 150 caracteres para nombre y 40 para teléfono.

Las demás opciones siguen permitiendo opiniones anónimas: Nombre y Teléfono permanecen visibles y opcionales, aunque estén vacíos. Cambiar el tipo no oculta, limpia ni deshabilita esos datos. Al consultar un registro se muestran los contactos históricos, aunque no sea Solicitud. El siguiente formulario de un listado queda limpio como antes.

El dictado reconoce «tipo solicitud» y selecciona su radio, actualizando la obligatoriedad del contacto. Proyecto y fecha siguen excluidos del dictado. La nueva opción se incorpora también a los filtros y listados usando el catálogo compartido de tipos; los filtros de consulta mantienen su select.

Género y Afrodescendiente se presentan como grupos de radio compactos y opcionales. Género conserva No indicado, Femenino, Masculino y No binario / Otro; Afrodescendiente conserva No indicado, Sí y No. No indicado es el valor predeterminado para formularios nuevos. Al editar o consultar se marca el valor histórico; No (`false`/`0`) se diferencia de No indicado (`null`). El dictado selecciona los radios correspondientes y puede volver a No indicado. No se agregan categorías ni se reclasifican opiniones.

No se agregan columnas ni migraciones: se reutilizan `feedback_type` (valor `request`), `full_name` y `phone`. No se modifican datos históricos, permisos, grupos, asignaciones, beneficiarios, períodos, cierres, enlaces o QR. La protección contra reenvíos del formulario público, el agradecimiento y la redirección a ASONACOP se conservan.

## Archivos para el servidor ya actualizado

Subir conjuntamente estos doce archivos, incluidos los nuevos:

```text
app/Models/BuzonEntry.php
app/Http/Requests/SaveBuzonEntryRequest.php
app/Http/Requests/SavePublicBuzonEntryRequest.php
app/Http/Controllers/TemporaryMaintenanceController.php
resources/views/buzon/form.blade.php
resources/views/buzon/public/form.blade.php
resources/views/buzon/partials/feedback-type.blade.php
resources/views/buzon/partials/demographic-radios.blade.php
public/css/buzon-feedback.css
public/js/buzon-feedback.js
public/js/buzon-dictation.js
public/js/buzon-voice-data.js
```

Con respaldo y mantenimiento manual, usar el modo existente `only=actualizacion-buzon-publico` con el controlador actualizado. Las tres migraciones anteriores, ya instaladas, se omiten y se reconstruyen las cachés. No ejecutar el modo general con seeders ni cambiar APP_KEY o reemplazar storage. No hay una migración nueva para Solicitud.

Después de subir los doce archivos, incluidos los dos parciales, el CSS y el JavaScript nuevos, ejecutar en el navegador sustituyendo únicamente el token:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-buzon-publico&token=TU_TOKEN_PRIVADO
```

No omitir `only`. La preparación del controlador no ejecuta cambios en el servidor. El controlador comprueba los archivos y referencias antes de cualquier comando y se detiene si falta alguno; después de ejecutarlo, verificar códigos 0 y recargar con Ctrl + F5.

Recargar con Ctrl + F5. Probar el formulario interno y el enlace público: seleccionar Solicitud, comprobar que no guarda con contactos vacíos, completar ambos y guardar. Cambiar a Sugerencia/Comentario/Queja debe dejar el contacto opcional. Revisar el filtro Solicitud, editar una solicitud, consultar una opinión histórica con contacto y continuar un listado comprobando que el siguiente formulario está limpio. No retirar mantenimiento hasta comprobar archivos/cachés; el envío público requiere retirar el bloqueo para la prueba funcional.

Pruebas aisladas: `BuzonFeedbackRequestTest`, `BuzonTest`, `PublicBuzonTest`, suites de permisos/listados/grupos y pruebas JavaScript de retroalimentación/dictado. No se invoca el controlador temporal real ni el servidor durante las pruebas.
