# Nacionalidad del beneficiario

El formulario `/reportes/nuevo` incorpora un selector **Nacionalidad**, con **Venezolana** seleccionada para cada nuevo beneficiario. Al guardar y preparar la siguiente persona, se restablece ese valor; al editar una persona, se carga su nacionalidad guardada, sin sustituir datos faltantes de registros históricos.

Las opciones se administran en `config/reports.php`, sección `beneficiary_options.nationalities`: Venezolana, Colombiana, Peruana, Ecuatoriana, Brasileña, Cubana, Dominicana, Española, Portuguesa, Italiana, Argentina, Chilena, Boliviana, China, Libanesa, Siria, Guyanesa, Estadounidense y Otra. También permite **Sin especificar**. Se valida la lista en el servidor; si un registro antiguo contiene una nacionalidad diferente, se conserva como opción adicional únicamente para editar ese beneficiario.

Es una lista práctica orientativa, no una clasificación de llegadas actuales. Como referencia histórica se utilizó el capítulo de Venezuela de [SIMN/CEMLA (2020), basado en el Censo 2011](https://simn-global.org/wp-content/uploads/2020/09/Book-and-Cover-SP.pdf#page=417), complementado con opciones regionales. País de nacimiento, nacionalidad y flujos de entrada no son conceptos equivalentes; la lista puede ajustarse según la población atendida por ASONACOP.

Se guarda en `beneficiaries.nationality`, se conserva en las copias a indicadores asociados y se muestra en los listados de beneficiarios del formulario y del detalle del registro. Los guardados individuales y por lote aceptan el dato. Los clientes que crean beneficiarios sin enviar este campo usan el valor predeterminado; en ediciones, omitirlo conserva el dato anterior. Enviar el campo vacío permite dejarlo sin especificar.

La migración agrega una columna nullable, sin valor predeterminado en la base ni actualización de registros anteriores. No se cambian los conteos, permisos, Buzón, filtros ni columnas de exportación.

## Servidor

Después de respaldar la base y subir los archivos nuevos y modificados, aplicar:

```sh
php artisan migrate --path=database/migrations/2026_10_27_120000_add_nationality_to_beneficiaries_table.php --force
php artisan view:clear
php artisan config:clear
```

Como alternativa a los comandos anteriores, el controlador temporal incluye el modo específico:

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=nacionalidad-beneficiarios&token=TU_TOKEN
```

Reemplazar `TU_TOKEN` por el `SERVER_MAINTENANCE_TOKEN` ya configurado en el `.env` del servidor. No publicar el token ni subir el `.env` local. Subir todos los archivos nuevos y modificados, incluidos el controlador temporal, esta migración, `config/reports.php`, los modelos, solicitudes, vistas y assets de filtros.

Este modo verifica primero que existan los archivos requeridos, limpia cachés, ejecuta **únicamente** la migración de nacionalidad y reconstruye las cachés de configuración, rutas y vistas. Se detiene ante cualquier error; repetirlo omite la migración ya aplicada y no cambia las nacionalidades guardadas. No ejecuta seeders, importaciones, cambios de permisos ni correcciones de parroquias. Tampoco modifica los conteos, resúmenes, asignaciones, Buzón, períodos, cierres, bloqueos o avisos de mantenimiento.

La comprobación de archivos valida su existencia, no que su contenido esté actualizado: es necesario subirlos todos. Los ajustes de filtros ya guardados en Git también deben estar subidos si aún no están en el servidor.

El modo temporal `indicadores-resumen` **no ejecuta esta nueva migración**; `nacionalidad-beneficiarios` tampoco instala las tablas de Indicadores Resumen. Si sigue pendiente aquel despliegue, ejecutar primero su modo específico y después el de nacionalidad. No usar el modo general sin `only`, pues contiene seeders antiguos.

Después de una respuesta correcta, recargar con Ctrl + F5 y comprobar el formulario y filtros. Cualquier bloqueo de mantenimiento se conserva hasta desactivarlo manualmente. Retirar o deshabilitar el acceso temporal después del despliegue según el procedimiento habitual. Preparar este archivo localmente no ejecuta cambios en el servidor.
