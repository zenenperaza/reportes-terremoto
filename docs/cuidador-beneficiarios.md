# Cuidador de beneficiarios menores de edad

En Registrar actividad y Editar beneficiario, una edad entera entre 0 y 17 años muestra y exige:

- Cédula del cuidador (texto, hasta 30 caracteres; conserva ceros iniciales y prefijos).
- Nombre del cuidador (hasta 150 caracteres; se guarda en mayúsculas).
- Parentesco del cuidador (select): Madre, Padre, Abuela, Abuelo, Tía, Tío, Hermana, Hermano, Madrastra, Padrastro, Tutor/a legal, Otro familiar, Cuidador/a no familiar y Otro. La lista se configura en `reports.beneficiary_options.caregiver_relationships` y se valida también en el servidor.

Un parentesco guardado anteriormente fuera de la lista se conserva como opción adicional únicamente al editar a esa misma persona. No se admiten nuevos textos libres en los registros ni al editar otra persona. Al autocompletar desde una cédula, un parentesco que no esté disponible en el selector se deja vacío para elegir una opción actual. No se modifican los parentescos históricos y este cambio de selector no añade otra migración.

Para edades de 18 años o más, estos campos se ocultan y se vacían. El servidor aplica la misma regla al registrar una persona, registrar un lote y editar. El guardado por voz usa la misma validación y no permite omitir al cuidador de un menor.

Los datos se guardan en tres columnas opcionales de `beneficiaries`, se recuperan al editar y se conservan en las copias de indicadores asociados. El detalle del registro incluye un apartado plegable con los cuidadores. No crea beneficiarios adicionales, no altera los conteos ni añade columnas a los Excel existentes. No modifica el Buzón.

Los beneficiarios históricos mantienen las tres columnas en NULL. No se deducen cuidadores a partir de nombres, teléfonos ni cédulas; al editar a un menor se solicitan los datos faltantes.

## Despliegue

Subir todos los archivos modificados, incluido `config/reports.php`, y los nuevos archivos del rasgo de validación y la migración. Antes de habilitar el formulario, aplicar exclusivamente:

```sh
php artisan migrate --path=database/migrations/2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php --force
php artisan optimize:clear
```

No requiere seeders, importaciones ni reemplazar la base del servidor. Conservar su `.env`. Los modos anteriores del controlador temporal no ejecutan esta migración; no usar el modo general antiguo para instalarla. Preparar un modo específico si el servidor no permite consola.
