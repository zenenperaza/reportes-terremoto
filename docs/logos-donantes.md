# Logo del donante y QR del Buzón

## Uso

En **Donantes → Crear/Editar donante** se configura **Logo del donante (opcional)**. Se usa en el centro del QR del Buzón de todos los proyectos asociados a ese donante. No hay campo de logo en Proyectos.

- PNG, JPG/JPEG o WebP; máximo 2 MB, dimensiones entre 32 y 2000 píxeles de ancho y alto.
- No se aceptan SVG, archivos corruptos ni extensiones ajenas a esos formatos.
- Editar sin subir imagen ni marcar Quitar conserva el logo actual.
- Subir otra imagen reemplaza el logo; **Quitar logo actual** vuelve al QR normal.
- No se puede subir y quitar simultáneamente.
- La carga respeta el acceso administrativo existente de Donantes.

Cambiar el logo afecta a los QR que se generen después de recargar el Buzón; no modifica los enlaces firmados ni las imágenes PNG ya descargadas. El proyecto mantiene su código y su propio enlace, aunque comparta logo con otros proyectos del mismo donante. Sin logo se genera el QR normal.

## Seguridad y lectura del QR

El QR conserva la URL completa y usa corrección HIGH al insertar logo, una placa blanca pequeña, proporción de imagen original y margen exterior de cuatro módulos. Si la imagen falla, tarda más de ocho segundos o no puede dibujarse, se ofrece el QR normal. Cambiar de proyecto descarta cargas tardías. La generación ocurre localmente en el navegador, sin enviar enlaces ni imágenes a servicios externos.

Los archivos se guardan con nombres aleatorios en el disco privado `local`, bajo `storage/app/private/donor-logos/`. No requiere `storage:link`. La ruta autenticada `/donantes/{donante}/logo` permite administradores o usuarios con **ver buzon**, **crear buzon** y al menos un proyecto asignado de ese donante. No permite explorar archivos; valida ruta y tipo de imagen, con cabeceras privadas y nosniff. El PNG descargado contiene el logo y puede compartirse.

`logo_path` no es asignable mediante el formulario ni se serializa en el modelo. Los archivos anteriores solo se retiran tras confirmar la actualización; ante fallos se limpia la nueva carga y se conserva la anterior. No se permite eliminar un donante con proyectos; cuando la eliminación del donante procede se limpia su archivo.

## Despliegue

Archivos de esta mejora, además de los cambios pendientes del Buzón público:

```text
app/Http/Controllers/DonanteController.php
app/Http/Controllers/DonorLogoController.php
app/Http/Controllers/BuzonController.php
app/Models/Donante.php
app/Support/DonorLogo.php
database/migrations/2026_11_17_140000_add_logo_to_donantes.php
resources/views/donantes/create.blade.php
resources/views/donantes/edit.blade.php
resources/views/donantes/form.blade.php
resources/views/buzon/index.blade.php
public/css/donor-logo.css
public/js/buzon-public-link.js
routes/web.php
```

Con respaldo y mantenimiento activado, aplicar únicamente:

```text
php artisan migrate --path=database/migrations/2026_11_17_140000_add_logo_to_donantes.php --force
```

Añade `logo_path` nullable a Donantes; no cambia donantes existentes, proyectos, opiniones, beneficiarios, permisos, períodos o cierres. No requiere seeders. Para publicar esta mejora junto con el Buzón público desde el navegador, usar el modo **only=actualizacion-buzon-publico** del controlador actualizado: ejecuta únicamente las tres migraciones específicas de origen, logo y protección contra duplicados, omitiendo las ya aplicadas, y cachés. Véase [guía de despliegue](despliegue-buzon-publico-logos.md). No usar el modo general con seeders.

Conservar y respaldar los logos privados del servidor; no sobrescribir `storage` al subir código. Renovar cachés habituales y recargar con Ctrl + F5. Probar subir imagen en Editar donante, generar QR de sus proyectos, descargar y escanear desde un teléfono. Compartir QR generados desde producción, no desde localhost.

La migración de Donantes fue aplicada en local. La propuesta inicial de logo en Proyectos se retiró del código antes de publicar; su columna nullable ya creada en la base local quedó sin uso, sin eliminar columnas ni datos. No se debe subir una migración de logo para Proyectos.

Pruebas: `DonorLogoTest`, `ProjectPlacesTest`, `PublicBuzonTest` y pruebas JavaScript del enlace/QR. Cubren permisos y proyectos del donante, carga/validación/reemplazo/quitar, limpieza tras fallos, logo compartido, enlaces sin cambios y recuperación a QR normal. Las pruebas usan datos ficticios y SQLite en memoria.
