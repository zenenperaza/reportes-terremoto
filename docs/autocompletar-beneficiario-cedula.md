# Autocompletar beneficiarios por cédula

En `/reportes/nuevo`, Cédula aparece primero. Al terminar de escribir (650 ms sin cambios) o salir del campo se consulta una coincidencia completa, no una búsqueda parcial. El campo continúa siendo opcional para personas sin cédula; no busca por cédula del cuidador.

La consulta exige los permisos existentes `registrar actividad` y `ver detalle de registros`, se limita a los registros visibles para el usuario y tiene un límite de 60 solicitudes por minuto. No cambia las reglas de equipos, ubicaciones ni permisos. Si el usuario no tiene acceso, puede seguir completando manualmente el formulario.

Se admiten cifras y prefijos V/E con espacios, puntos o guiones. Se conservan los ceros iniciales. Si no se indica prefijo, se consideran V, E y las cédulas sin prefijo; cuando hay identidades o prefijos incompatibles, se pide verificación manual sin devolver sus datos.

Se toma el último beneficiario guardado visible para completar nombre, edad registrada, sexo, nacionalidad, teléfono, discapacidad, etnia, embarazo/lactancia y cuidador. No se calcula una edad actual a partir de una fecha inexistente: debe revisarse, al igual que el cuidador. Se conservan las correcciones que el registrador haga mientras la consulta está en curso.

No se copian proyecto, período, ubicación, indicador, estado de reporte, consentimiento ni condición recurrente. Esta última se calcula mediante una búsqueda independiente dentro del proyecto seleccionado y se verifica nuevamente al guardar. El autocompletado no guarda ni actualiza registros automáticamente.

Cambiar la cédula limpia solo los datos que el autocompletado había cargado y que no se hayan corregido manualmente. Las consultas antiguas se cancelan o se ignoran. Al guardar, pasar a otra persona o abrir la edición se invalida la consulta pendiente. En edición no se autocompleta desde otra identidad.

El endpoint POST `/beneficiarios/buscar-cedula` evita incluir la cédula en la URL; sus respuestas llevan `Cache-Control: no-store, private`. La bitácora general no almacena el cuerpo de la búsqueda. Solo devuelve los campos personales necesarios, no identificadores de registros, datos de otros proyectos ni conteos de coincidencias ocultas.

La mejora no requiere migración adicional; presupone aplicada la migración de cuidadores `2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php`. Subir el controlador de consulta, rutas, middleware de bitácora, formulario y el nuevo `public/js/beneficiary-id-autofill.js`; reconstruir cachés de rutas y vistas después de la subida.
