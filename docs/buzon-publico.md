# Enlaces públicos del Buzón por proyecto

## Uso

En **Buzón**, un usuario con **ver buzon** y **crear buzon** encuentra la sección **Enlace público del Buzón**. Al seleccionar un proyecto activo asignado a su usuario aparece su enlace junto a **Copiar enlace** y **Abrir formulario público**. Los administradores pueden seleccionar cualquier proyecto activo, siempre con los permisos correspondientes. No se crean permisos nuevos.

El enlace firmado tiene este formato orientativo (los valores de ejemplo no son utilizables):

```text
https://registros.asonacop.org/buzon-publico/1/vnz-pca202380-pd2023237/7?signature=FIRMA
```

Incluye el código del proyecto en un formato legible para URL, además del identificador del proyecto y del usuario responsable. Los códigos con `/` o espacios se normalizan con guiones. El formulario muestra el código original completo. Los identificadores diferencian proyectos aunque sus códigos normalizados coincidan.

El enlace contiene una firma criptográfica de Laravel: cambiar el proyecto, el código, el responsable o los parámetros invalida la firma. **Compartir el enlace permite a cualquier persona que lo reciba enviar opiniones**, sin cuenta ni inicio de sesión. No es un enlace para consultar información. No usa el token del mantenimiento ni una API externa.

### Código QR para compartir

Al seleccionar un proyecto aparece también su QR y el botón **Descargar QR (PNG)**. La imagen contiene exactamente el mismo enlace firmado que Copiar enlace, incluido su responsable. Lleva el título Buzón ASONACOP y el código original del proyecto debajo del símbolo, sin tapar sus módulos ni su margen blanco. El nombre del archivo incluye el código del proyecto y su identificador.

La sección interna coloca el selector y el enlace en una columna compacta y el QR en un panel lateral independiente, con un estado vacío antes de seleccionar. El selector conserva una altura de 46 px: no se estira con la imagen. En pantallas pequeñas los paneles se apilan y los botones ocupan el ancho disponible. El aviso de entorno local queda separado al pie de la sección. Los colores se adaptan al modo oscuro; el símbolo QR conserva blanco y negro para su lectura.

Si se carga un **logo en Crear/Editar donante**, los QR del Buzón de sus proyectos lo incorporan en el centro y aumentan su corrección de errores. Sin logo o si falla su carga, se mantiene el QR normal. Requiere una migración adicional: consultar [Logo del donante y QR del Buzón](logos-donantes.md).

El QR se genera en el navegador con la biblioteca local [Project Nayuki, versión 1.8.0](https://github.com/nayuki/QR-Code-generator/releases/tag/v1.8.0), con licencia MIT conservada en su cabecera. No se envía la URL a un servicio de QR ni se usan CDN, extensiones PHP o dependencias nuevas de Composer/npm. Se guarda la imagen usando la descarga del navegador, sin crear archivos en el servidor ni entradas adicionales en la base de datos.

Cambiar el proyecto reemplaza el QR y el archivo descargable; quitar la selección los retira. Si no puede generarse el QR, el enlace sigue disponible para copiarlo. Sin JavaScript no hay generación del QR. El QR respeta las mismas condiciones de validez y permisos del enlace y no permite consultar opiniones. Si se genera desde localhost/127.0.0.1, apunta a ese equipo: para compartirlo con otras personas hay que generarlo desde el dominio publicado. La interfaz local muestra esta advertencia.

## Formulario público

El proyecto queda fijo por el enlace; no puede seleccionarse ni modificarse mediante campos ocultos. Se recoge fecha, lugar, valoración y tipo de retroalimentación, además del comentario o propuesta de mejora. Los datos personales y demográficos son opcionales; permite una opinión anónima. Se exige al menos un texto de comentario o mejora.

Después de guardar correctamente el envío, el navegador se redirige mediante HTTP 303 (GET) a **https://asonacop.org/**. Es un destino fijo: no acepta otra dirección desde el formulario, no añade datos personales, identificadores ni la firma del enlace al destino y no envía el referente. La política CSP permite este destino para no bloquear la redirección del formulario. Los errores de validación permanecen en el formulario público para corregirlos; no se redirigen a la web institucional. Al volver a abrir el enlace después de guardar, el formulario queda limpio.

No se devuelven el identificador ni los datos de la opinión guardada. No hay listados, conteos, datos del usuario responsable, otras opiniones, enlaces internos ni acceso a beneficiarios. Tampoco se agrupan automáticamente los envíos en listados de carga interna.

La página es independiente de la interfaz interna, adaptable al teléfono y sin servicios externos, dictado de voz o analítica. Se conserva protección CSRF. Si el formulario expira, vuelve a la página pública sin exigir inicio de sesión y sin cerrar una sesión interna que estuviera abierta.

Los errores públicos no muestran trazas ni detalles del servidor, incluso si debug estuviera activado. Aun así, producción debe mantener `APP_DEBUG=false`. Las páginas públicas llevan cabeceras para no almacenar ni indexar el contenido, no transmitir el enlace como referente y no incrustarlo en marcos.

## Registro, permisos y grupos

Cada envío crea **solo una opinión** en `buzon_entries`:

- `created_by` queda vacío: no se atribuye falsamente el formulario a un empleado.
- `submitted_via_public_link` se fija en verdadero por el servidor.
- `public_recipient_id` identifica al responsable cuyo enlace se utilizó.
- El proyecto se obtiene exclusivamente de la URL firmada.
- No crea beneficiarios, registros de actividades ni listados del Buzón.

El listado interno añade **Usuario** y **Origen: Enlace externo / Interno**. Para una carga interna, Usuario muestra a quien la registró, incluso si su cuenta se eliminó lógicamente. Si no se conserva el autor, indica Usuario no disponible. Para una carga externa muestra Externo (sin usuario) y, por separado, el responsable del enlace: no atribuye el envío anónimo al empleado. Nombre sigue siendo el nombre opcional del participante. Estas columnas no requieren otra migración adicional a la de origen público.

En el detalle se identifica también al responsable del enlace. La consulta requiere **ver buzon**, el proyecto asignado y ser el responsable o miembro directo de su grupo activo; los administradores conservan alcance global con el permiso. Editar/eliminar exige el permiso de la acción; para compañeros, también grupo activo con edición cruzada habilitada. Las opiniones antiguas sin autor no se vuelven visibles por este cambio.

Los formularios públicos se excluyen de la bitácora general, incluso si quien los abre tiene una sesión de SIA: no se duplican textos, teléfonos o datos sensibles en dicha bitácora. Se mantienen los controles existentes para consultas y modificaciones internas.

## Validez del enlace y protección de envíos

El enlace no tiene una fecha de caducidad fija ni un botón de revocación individual en esta versión. En cada apertura y envío se comprueba que el proyecto siga activo, su código no haya cambiado, el responsable exista y esté activo, conserve **crear buzon** y siga asignado al proyecto (excepto el alcance global del administrador). Desactivar esas condiciones impide nuevos envíos, sin eliminar las opiniones ya recibidas.

Se limita el envío a **10 intentos por minuto y 100 por hora por IP**, compartidos entre proyectos, más un campo trampa para automatismos. No equivale a un CAPTCHA ni garantiza ausencia de spam; una red compartida puede alcanzar ese límite con varios participantes. El límite de apertura es 60 peticiones por minuto. Las claves de límite de envío usan un hash de la IP.

El bloqueo de mantenimiento de SIA impide también las cargas públicas. Un aviso programado permite enviar hasta la hora de bloqueo y no cambia al abrir el formulario público. Esta función no modifica los períodos ni sus cierres.

## Despliegue

Requiere una nueva migración **aditiva**, sin seeders ni importaciones. No cambia asignaciones o permisos ni agrupa opiniones históricas. Con copia de seguridad y bloqueo activado, subir conjuntamente:

```text
app/Http/Controllers/BuzonController.php
app/Http/Controllers/PublicBuzonController.php
app/Http/Middleware/PublicBuzonAccess.php
app/Http/Requests/SavePublicBuzonEntryRequest.php
app/Models/BuzonEntry.php
app/Providers/AppServiceProvider.php
app/Support/PublicBuzonLink.php
bootstrap/app.php
database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php
resources/views/buzon/index.blade.php
resources/views/buzon/form.blade.php
resources/views/buzon/public/form.blade.php
resources/views/buzon/public/unavailable.blade.php
public/css/buzon.css
public/css/buzon-public.css
public/js/buzon-public-link.js
public/vendor/qrcodegen/qrcodegen.js
public/vendor/qrcodegen/README.md
routes/web.php
```

Aplicar únicamente esta migración y renovar las cachés habituales:

```text
php artisan migrate --path=database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php --force
```

Los modos antiguos del controlador temporal **no aplican esta migración**. Ya está preparado el modo **only=actualizacion-buzon-publico** para esta migración, la de logos de Donantes y las cachés: [guía de despliegue](despliegue-buzon-publico-logos.md). No ejecutar el modo general del controlador: contiene seeders ajenos a este cambio.

Los enlaces se generan con el dominio del entorno donde se abre SIA: un enlace de `127.0.0.1` es solo una prueba local y no debe compartirse como enlace de producción. Compartir el enlace generado desde el servidor HTTPS. No cambiar `APP_KEY` para desplegar esta función.

Después, probar con una ventana privada: abrir el enlace, enviar una opinión sin datos de identidad, comprobar la redirección a https://asonacop.org/ y verificar que el responsable puede consultarla en el Buzón interno. Revisar permisos/grupos, proyecto fijo y bloqueo antes de quitar mantenimiento manualmente.

Pruebas: `PublicBuzonTest`, las cuatro suites existentes del Buzón/listados/permisos/grupos, `buzon-public-link.test.cjs` y `buzon-public-qr.test.cjs`. Usan SQLite en memoria y datos ficticios, sin envíos al servidor. Las pruebas QR verifican la URL firmada completa, cambios de proyecto, descarga PNG, margen blanco de cuatro módulos, leyenda y recuperación ante errores. La lectura de símbolos de dos proyectos se comprueba además con un decodificador independiente durante el desarrollo, sin usar enlaces reales de usuarios.

Agregar QR sin logo no requiere otra migración. Para incorporarlo sobre el Buzón público ya instalado basta con subir `resources/views/buzon/index.blade.php`, `public/css/buzon.css`, `public/js/buzon-public-link.js` y los dos archivos de `public/vendor/qrcodegen/`, renovar vistas y recargar con Ctrl + F5.

Para el logo central se configura la imagen en **Donantes**, no en Proyectos: todos los proyectos del donante usan ese logo. Esta mejora sí requiere una migración propia y archivos adicionales; véase [Logos de donantes](logos-donantes.md).
