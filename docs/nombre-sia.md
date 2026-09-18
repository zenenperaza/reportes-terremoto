# Nombre del sistema: SIA

Nombre corto: **SIA**. Nombre completo: **Sistema de Información ASONACOP**.

Se actualizaron encabezados, pie de página, títulos de las vistas, pantalla de mantenimiento, notificaciones de seguridad, nombre visible del remitente, manifiesto de instalación y pantalla sin conexión. Se conserva el emblema de ASONACOP.

## Publicación en cPanel

Subir las versiones actuales de:

- `resources/views/` (vistas actualizadas, incluyendo `layouts/app.blade.php`).
- `app/Notifications/EmailTwoFactorCodeNotification.php` y `ResetPasswordNotification.php`.
- `config/app.php` y `config/mail.php`.
- `public/manifest.webmanifest`, `public/offline.html`, `public/service-worker.js` y `public/js/pwa.js`.

Después, usar el mantenimiento existente con `only_cache=1` y el token privado configurado para reconstruir las cachés. No se requieren migraciones ni cambios de datos.

No sobrescribir el `.env` del servidor. Los nombres visibles definidos en configuración no dependen del antiguo `APP_NAME`; sus usos técnicos para sesiones y caché se conservan. No se cambia la dirección del remitente ni las credenciales de correo.

Se mantiene el identificador de la PWA y se incrementa la versión de su caché para actualizar la pantalla sin conexión. El nombre de una aplicación ya instalada puede tardar en actualizarse según el navegador.

Las rutas internas, nombres de respaldos, datos históricos y el archivo de referencia `database/reference/unicef-terremoto-referencia.xlsx` no se renombran: no son la identidad visible del sistema y sus referencias siguen funcionando.
