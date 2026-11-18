# Despliegue: Buzón público, QR y logo de Donantes

Preparado en `TemporaryMaintenanceController`, modo **actualizacion-buzon-publico**. Preparar este archivo no ejecuta mantenimiento ni publica cambios en el servidor.

## Antes de ejecutar

1. Respaldar base de datos y archivos. Activar el mantenimiento desde SIA manualmente.
2. Subir todos los archivos de ejecución enumerados abajo, incluidos los nuevos. No subir `.env`, la base local ni reemplazar el directorio `storage` del servidor. No cambiar `APP_KEY`.
3. Confirmar que el Buzón del servidor ya tiene sus cuatro permisos y listados (actualizaciones previas). Este modo no instala esas funcionalidades anteriores ni permisos.
4. Usar `APP_DEBUG=false` en producción. Mantener privado el token de mantenimiento; si se compartió anteriormente, sustituirlo en el servidor.

## Archivos que subir

### Si ya se aplicó la versión inicial del Buzón público

Para la corrección de envíos duplicados, la confirmación de guardado y la identificación visual del donante en el selector, actualizar estos doce archivos sobre esa instalación completa:

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/PublicBuzonController.php
app/Http/Requests/SavePublicBuzonEntryRequest.php
app/Models/BuzonEntry.php
database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php
resources/views/buzon/public/form.blade.php
resources/views/buzon/public/thanks.blade.php
public/css/buzon-public.css
public/js/buzon-public-submit.js
resources/views/buzon/index.blade.php
public/css/buzon.css
public/js/buzon-share-project.js
```

Ejecutar el mismo modo `only=actualizacion-buzon-publico` con el controlador actualizado. Las dos primeras migraciones ya aplicadas se omitirán; solo se añadirá `public_submission_key`, sin borrar duplicados existentes. Recargar el formulario con Ctrl + F5 antes de probar. No ejecutar el controlador antiguo para esta corrección: no conoce la tercera migración.

### Instalación completa

Incluye todos los archivos de ejecución pendientes en Git para esta actualización. Las pruebas y documentación pueden conservarse en el repositorio; no son necesarias para que el servidor ejecute la función.

```text
app/Http/Controllers/TemporaryMaintenanceController.php
app/Http/Controllers/BuzonController.php
app/Http/Controllers/PublicBuzonController.php
app/Http/Controllers/DonanteController.php
app/Http/Controllers/DonorLogoController.php
app/Http/Middleware/PublicBuzonAccess.php
app/Http/Requests/SavePublicBuzonEntryRequest.php
app/Models/BuzonEntry.php
app/Models/Donante.php
app/Providers/AppServiceProvider.php
app/Support/PublicBuzonLink.php
app/Support/DonorLogo.php
bootstrap/app.php
database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php
database/migrations/2026_11_17_140000_add_logo_to_donantes.php
database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php
resources/views/buzon/form.blade.php
resources/views/buzon/index.blade.php
resources/views/buzon/public/form.blade.php
resources/views/buzon/public/unavailable.blade.php
resources/views/buzon/public/thanks.blade.php
resources/views/donantes/create.blade.php
resources/views/donantes/edit.blade.php
resources/views/donantes/form.blade.php
public/css/buzon.css
public/css/buzon-public.css
public/css/donor-logo.css
public/js/buzon-public-link.js
public/js/buzon-share-project.js
public/js/buzon-public-submit.js
public/vendor/qrcodegen/qrcodegen.js
public/vendor/qrcodegen/README.md
routes/web.php
```

Se comprueban también dependencias ya instaladas: `SaveBuzonEntryRequest`, `RecordAuditLog`, `User`, `UserGroup`, `Proyecto`, `routes/maintenance.php`, `public/icons/asonacop-app.png` y los archivos locales de jQuery/Select2 en `public/vendor/select2/`. Si falta alguna, se detiene antes de ejecutar comandos. Conservar la ubicación pública configurada en el servidor al copiar los archivos de `public/`.

## Ejecutar en el navegador

Sustituir `TU_TOKEN_PRIVADO` por el valor de `SERVER_MAINTENANCE_TOKEN` del servidor. No enviar esa dirección con el token a otras personas.

```text
https://registros.asonacop.org/ejecutar-migraciones-temp?only=actualizacion-buzon-publico&token=TU_TOKEN_PRIVADO
```

El parámetro es **only**, no `nly`. Esa errata ahora se rechaza sin ejecutar comandos. No omitir `only`: el modo general histórico contiene seeders y no corresponde a esta actualización. No combinar este modo con `only_cache`, `import_excel`, `apply` o `decisions`.

La página debe comenzar con **ACTUALIZACIÓN DE BUZÓN PÚBLICO, QR Y LOGOS DE DONANTES** y terminar sin errores. Ejecuta, en este orden:

```text
optimize:clear
cache:clear
migrate --path=database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php --force
migrate --path=database/migrations/2026_11_17_140000_add_logo_to_donantes.php --force
migrate --path=database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php --force
config:cache
route:cache
view:cache
```

Finalmente invalida la caché de permisos, sin modificar permisos ni asignaciones. Las migraciones ya registradas se omiten. Ante un comando fallido no se ejecutan los siguientes; corregir el problema y repetir el mismo modo.

No ejecuta seeders, importaciones, otras migraciones, correcciones geográficas ni respaldos automáticos. No crea ni elimina opiniones, beneficiarios, registros o listados. La primera migración añade campos de origen: las opiniones existentes quedan internas y sin responsable externo, conservando sus autores. La segunda añade `donantes.logo_path` opcional. La tercera añade `buzon_entries.public_submission_key`, opcional y único: no rellena claves históricas ni elimina duplicados anteriores. No se agregan logos automáticamente, no se cambia el reloj y no se activan/cancelan bloqueos ni avisos.

## Comprobar después

- Recargar con Ctrl + F5. Verificar que el Buzón muestra selección de proyecto, enlace público, QR y columnas Usuario/Origen. El selector muestra nombre/logo del donante junto al código y nombre del proyecto; al seleccionar, también aparece una identificación del donante al lado (debajo en pantallas pequeñas). Sin logo, se conserva nombre e inicial.
- En Editar donante, subir una imagen PNG/JPG/WebP (máximo 2 MB); guardar y generar QR de un proyecto suyo. Descargar y escanear el PNG desde un teléfono. Si se reemplaza el logo, regenerar las imágenes a compartir.
- Los enlaces generados desde localhost solo sirven en local. Compartir únicamente los generados desde producción HTTPS.
- El formulario público respeta el bloqueo de mantenimiento. Para la prueba completa de envío, retirar el bloqueo manualmente cuando el despliegue esté comprobado y abrir el enlace en una ventana privada.
- Enviar una opinión de prueba: debe aparecer «Gracias por su opinión, fue registrada satisfactoriamente» y, tras cinco segundos, ir a `https://asonacop.org/`. Probar doble clic: solo debe crear una opinión. Comprobar Origen = Enlace externo, Usuario = Externo (sin usuario) y el responsable del enlace en el listado interno; no debe crear beneficiarios ni listados internos.
- Verificar que solo usuarios autorizados del proyecto/grupo consultan las opiniones y que modificar las de compañeros requiere edición cruzada y el permiso de la acción.

Deshabilitar o retirar la ruta/controlador temporal cuando ya no se necesite. La preparación no hace git add, commit, push ni despliegue.
