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

Si se carga un **logo en Crear/Editar donante**, los QR de sus proyectos muestran **donante a la izquierda y ASONACOP a la derecha** en la placa blanca central, con mayor corrección de errores. El logo institucional es `public/assets/images/asonacop/logo.png`. Sin logo del donante o si este falla, se mantiene ASONACOP solo. Si no carga ASONACOP, se genera el QR sin logos, nunca con el donante solo. La carga de logos de Donantes requiere su migración; la composición de ambos logos es una mejora visual sin otra migración: consultar [Logo del donante y QR del Buzón](logos-donantes.md).

El selector «Proyecto para compartir» reutiliza Select2 local para mostrar el logo junto al nombre del donante y el código/nombre del proyecto, tanto en sus opciones como en la selección. Se puede buscar por esos textos. También aparece una tarjeta del donante al lado, o debajo en móvil. Sin logo o si falla la imagen, se muestra la inicial sin perder el nombre. Sin Select2, sigue disponible el select nativo y la tarjeta seleccionada. El script `public/js/buzon-share-project.js` sincroniza el cambio con el enlace y QR existentes; no altera permisos, firmas ni proyectos. Esta mejora visual no agrega migraciones.

El QR se genera en el navegador con la biblioteca local [Project Nayuki, versión 1.8.0](https://github.com/nayuki/QR-Code-generator/releases/tag/v1.8.0), con licencia MIT conservada en su cabecera. No se envía la URL a un servicio de QR ni se usan CDN, extensiones PHP o dependencias nuevas de Composer/npm. Se guarda la imagen usando la descarga del navegador, sin crear archivos en el servidor ni entradas adicionales en la base de datos.

Cambiar el proyecto reemplaza el QR y el archivo descargable; quitar la selección los retira. Si no puede generarse el QR, el enlace sigue disponible para copiarlo. Sin JavaScript no hay generación del QR. El QR respeta las mismas condiciones de validez y permisos del enlace y no permite consultar opiniones. Si se genera desde localhost/127.0.0.1, apunta a ese equipo: para compartirlo con otras personas hay que generarlo desde el dominio publicado. La interfaz local muestra esta advertencia.

## Formulario público

El proyecto queda fijo por el enlace; no puede seleccionarse ni modificarse mediante campos ocultos. La retroalimentación aparece primero con cuatro radios: Sugerencia, Comentario, Queja y Solicitud. Se recoge fecha, lugar, valoración y comentario o propuesta de mejora. Los datos personales y demográficos son opcionales, excepto Nombre y Teléfono que son obligatorios al elegir Solicitud. Las otras opciones permiten anonimato. Se exige al menos un texto de comentario o mejora. Véase [Solicitudes y contacto condicional](buzon-solicitudes.md).

Después de guardar correctamente, HTTP 303 dirige a una confirmación GET en el mismo enlace firmado. Muestra **Gracias por su opinión, fue registrada satisfactoriamente** y después de cinco segundos redirige a **https://asonacop.org/** mediante meta refresh, también sin JavaScript; incluye un enlace para ir inmediatamente. Es un destino fijo, sin datos personales, identificadores ni firma del enlace, y sin referente. La confirmación se habilita en la sesión solo tras un guardado exitoso o un reintento ya guardado, y no muestra datos de la opinión. Los errores de validación permanecen en el formulario público; no muestran confirmación. Al volver a abrir el enlace, el formulario queda limpio.

Cada formulario contiene un identificador aleatorio de envío, separado de CSRF. El servidor deriva una clave vinculada al proyecto y responsable y la guarda en `public_submission_key`, con restricción única en la base de datos y dentro de la transacción con bloqueo del proyecto. Reenviar el mismo formulario (incluso sin conservar la sesión) devuelve confirmación sin insertar ni actualizar otra opinión. El botón también se deshabilita durante el envío. Formularios nuevos tienen claves nuevas: no se deduplican opiniones por nombres, textos, IP o datos personales. No se borran ni se unen duplicados anteriores. Los límites de envío siguen aplicándose a los reintentos.

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

La instalación inicial requiere la migración aditiva de origen; la protección contra duplicados agrega otra migración aditiva, ambas sin seeders ni importaciones. No cambia asignaciones o permisos ni agrupa opiniones históricas. Para publicación completa con logos y protección contra duplicados, usar [la guía de despliegue](despliegue-buzon-publico-logos.md). Los archivos base del Buzón público son:

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
database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php
resources/views/buzon/index.blade.php
resources/views/buzon/form.blade.php
resources/views/buzon/public/form.blade.php
resources/views/buzon/public/thanks.blade.php
resources/views/buzon/public/unavailable.blade.php
public/css/buzon.css
public/css/buzon-public.css
public/js/buzon-public-submit.js
public/js/buzon-public-link.js
public/vendor/qrcodegen/qrcodegen.js
public/vendor/qrcodegen/README.md
routes/web.php
```

Aplicar únicamente estas migraciones del Buzón público y renovar las cachés habituales:

```text
php artisan migrate --path=database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php --force
php artisan migrate --path=database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php --force
```

El controlador temporal actualizado tiene el modo **only=actualizacion-buzon-publico** para las tres migraciones específicas (origen, logos de Donantes e identificador de envío) y las cachés: [guía de despliegue](despliegue-buzon-publico-logos.md). Las ya aplicadas se omiten. No ejecutar el modo general del controlador: contiene seeders ajenos a este cambio.

Los enlaces se generan con el dominio del entorno donde se abre SIA: un enlace de `127.0.0.1` es solo una prueba local y no debe compartirse como enlace de producción. Compartir el enlace generado desde el servidor HTTPS. No cambiar `APP_KEY` para desplegar esta función.

Después, probar con una ventana privada: abrir el enlace, enviar una opinión sin datos de identidad, comprobar la redirección a https://asonacop.org/ y verificar que el responsable puede consultarla en el Buzón interno. Revisar permisos/grupos, proyecto fijo y bloqueo antes de quitar mantenimiento manualmente.

Pruebas: `PublicBuzonTest`, las cuatro suites existentes del Buzón/listados/permisos/grupos, `buzon-public-link.test.cjs` y `buzon-public-qr.test.cjs`. Usan SQLite en memoria y datos ficticios, sin envíos al servidor. Las pruebas QR verifican la URL firmada completa, cambios de proyecto, descarga PNG, margen blanco de cuatro módulos, leyenda y recuperación ante errores. La lectura de símbolos de dos proyectos se comprueba además con un decodificador independiente durante el desarrollo, sin usar enlaces reales de usuarios.

Agregar QR sin logo no requiere otra migración. Para incorporarlo sobre el Buzón público ya instalado basta con subir `resources/views/buzon/index.blade.php`, `public/css/buzon.css`, `public/js/buzon-public-link.js` y los dos archivos de `public/vendor/qrcodegen/`, renovar vistas y recargar con Ctrl + F5.

Para el logo central se configura la imagen en **Donantes**, no en Proyectos: todos los proyectos del donante usan ese logo. Esta mejora sí requiere una migración propia y archivos adicionales; véase [Logos de donantes](logos-donantes.md).
