# Recurrencia automática dentro del proyecto

Al registrar una nueva atención, **Recurrente** se calcula automáticamente y no se selecciona manualmente:

- Se busca en todo el proyecto seleccionado, sin limitar por indicador, actividad, lugar, período, registrador o equipo.
- Con cédula: se normalizan espacios, puntos, guiones y mayúsculas; se conserva el prefijo V/E y los ceros iniciales. Una cédula explícitamente V no coincide con una explícitamente E.
- Sin cédula: se exige coincidencia de nombre completo, edad y sexo. La edad debe coincidir exactamente; esta alternativa no equivale a identificar inequívocamente a una persona. Los homónimos o edades actualizadas requieren revisar los datos de identidad.
- La cédula del cuidador nunca se utiliza como cédula del beneficiario.
- Si solo tiene registros en otro proyecto, la nueva atención se guarda como **No recurrente**.

## Seguridad y persistencia

`POST /beneficiarios/verificar-recurrencia-proyecto` requiere permiso **registrar actividad** y acceso al proyecto (o administrador). Devuelve únicamente si hay coincidencia, no perfiles de otras personas, autores, conteos ni identificadores. Los datos personales no van en la URL ni se duplican en el payload de la bitácora. La respuesta no se almacena en caché.

`BeneficiaryRecurrence` comparte la regla entre la pantalla y el guardado. Las altas individuales y por lote recalculan la condición en el servidor, ignorando el valor enviado por el cliente. El bloqueo transaccional del proyecto serializa las altas simultáneas. Las copias en indicadores asociados conservan la misma condición que la atención principal; no convierten su propia primera atención en recurrente.

El formulario espera la comprobación antes de guardar. Al cambiar de proyecto o identidad se descartan respuestas anteriores. El dictado consulta esta misma regla y no puede reemplazar la condición calculada.

No se recalculan registros históricos ni se cambia su comportamiento de edición; corregir una atención existente no crea una nueva asistencia ni la reclasifica automáticamente. El endpoint GET anterior de alertas por actividad se conserva por compatibilidad, pero ya no se utiliza en este formulario.

## Despliegue

Este ajuste no necesita migración ni actualización masiva de beneficiarios. Publicar el servicio, controlador, rutas, middleware de bitácora, requests de alta, vista y archivos JavaScript modificados; renovar cachés de Laravel. Los cambios anteriores pendientes del cuidador tienen su propia migración.

Pruebas: `BeneficiaryProjectRecurrenceTest.php` y `beneficiary-recurrence.test.cjs`, junto con las pruebas de autocompletado, dictado y edición.
