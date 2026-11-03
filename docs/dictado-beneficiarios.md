# Dictado de beneficiarios: cédula primero

El dictado de `/reportes/nuevo` utiliza `public/js/beneficiary-voice.js` para interpretar los datos y coordinar el autocompletado existente de `beneficiary-id-autofill.js`.

## Uso

- Adulto: `Cédula V18456782, nombre María González, edad 34 años, mujer, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante`.
- Menor: `Sin cédula, nombre Ana Pérez, edad 12 años, mujer, cédula del cuidador V12345678, nombre del cuidador Carmen Pérez, parentesco Madre`.
- Para guardar por voz, termine con el comando explícito `guardar beneficiario`. El botón **Aplicar datos** solo completa el formulario, incluso si la transcripción contiene ese comando.
- Revise los campos antes de guardar. Se aceptan cifras y dígitos individuales dictados (`uve uno ocho cuatro cinco seis siete ocho dos`); expresiones numéricas ambiguas como `dieciocho millones` requieren corrección manual.

## Comportamiento

1. Los resultados provisionales se muestran en la transcripción sin completar campos ni consultar cédulas incompletas.
2. Con una cédula confirmada, se espera la consulta autorizada existente. No se amplían los permisos ni la visibilidad de registros.
3. Después se aplican los campos dictados explícitamente, por ejemplo una edad o teléfono actualizado, conservando las correcciones manuales realizadas durante la consulta.
4. Los datos del cuidador se interpretan por separado; no se busca su cédula como si fuera la del beneficiario.
5. Una consulta ambigua o fallida, o un dato no interpretado, impide el guardado automático. Puede revisar y guardar manualmente.
6. Guardar conserva las validaciones actuales: consentimiento, nombre, edad, sexo, recurrencia y datos del cuidador para menores, además de los encabezados y permisos del registro.

Al limpiar, editar otro beneficiario, salir u ocultar la página se cancelan los resultados pendientes de voz. El micrófono no se reinicia automáticamente. El consentimiento existente del beneficiario no se modifica ni se completa por voz.

## Comprobación

Las pruebas de JavaScript cubren el analizador, consultas demoradas, correcciones, cancelación, resultados provisionales y el comando de guardado con un reconocedor simulado. Las pruebas de Laravel usan SQLite en memoria. La precisión del reconocimiento real debe comprobarse con el micrófono y el navegador del registrador; no está cubierta por los simuladores.

Para desplegar este ajuste se requieren ambos archivos JavaScript y `resources/views/reports/create.blade.php`. No añade una migración nueva; los cambios anteriores del cuidador sí requieren su migración propia.
