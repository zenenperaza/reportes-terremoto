# Aviso de cierre de período al iniciar sesión

SIA muestra un aviso a administradores, coordinadores, registradores y otros usuarios activos al completar el inicio de sesión durante los cinco días previos al último día del mes, incluido el propio último día. Por ejemplo:

- Noviembre de 2026: del **25 al 30 de noviembre**.
- Un mes de 31 días: desde el día 26 hasta el 31.
- Febrero de 2027: del 23 al 28; febrero de 2028: del 24 al 29.

El cálculo usa **America/Caracas**, no la zona horaria del equipo del usuario ni la del sistema operativo del servidor. Se refiere al período activo configurado en SIA mediante `ReportPeriod::current()`. Si está cerrado o corresponde a otro mes, no se anuncia un cierre próximo. No se buscan ni se alteran períodos históricos.

## Comportamiento

El aviso informa que se aproxima el cierre e identifica el período y la fecha de fin de mes. Advierte que, una vez cerrado, no podrán agregarse, editarse ni eliminarse registros o beneficiarios de ese período porque los datos serán enviados al donante. Pide completar y revisar la información pendiente.

Se presenta como ventana con **Entendido**. También se puede cerrar con Escape. Si el navegador no permite ventanas `dialog`, queda como aviso visible; sin JavaScript continúa visible como texto. No solicita permisos de notificaciones de escritorio ni depende de un servicio externo.

El evento de autenticación completa encola el aviso en la sesión. Esto incluye acceso normal, autenticación de dos pasos después de verificar correctamente el código y recuperación de sesión mediante Recordarme. No se muestra por credenciales o códigos incorrectos ni durante el paso previo de envío del código.

La primera página autenticada que se renderiza consume el aviso, incluso si se ingresó con Recordarme. Cambiar de pantalla o recargar no lo repite. Un nuevo inicio de sesión dentro de la ventana vuelve a mostrarlo. Antes de mostrarlo se comprueba de nuevo que el período siga abierto y dentro de las fechas previstas, para no mostrar avisos pendientes desactualizados.

El aviso **no programa ni ejecuta el cierre**, no cambia permisos, no modifica registros y no envía datos al donante. Las reglas existentes de cierre y bloqueo siguen siendo las mismas. No se crean notificaciones en la base de datos ni se usa almacenamiento local del navegador. Una sesión que ya estaba abierta antes de la ventana no recibe un aviso en vivo: esta función se dispara al iniciar sesión.

## Despliegue

No requiere migraciones ni cambios en `.env`. Subir conjuntamente:

```text
app/Support/PeriodClosingNotice.php
app/Providers/AppServiceProvider.php
resources/views/layouts/app.blade.php
resources/views/layouts/partials/period-closing-notice.blade.php
public/js/period-closing-notice.js
public/css/period-closing-notice.css
```

Renovar las cachés de código/vistas por el procedimiento habitual y recargar el navegador. Para los listados del Buzón pendientes, aplicar además su migración correspondiente: esta mejora de aviso no la sustituye ni la ejecuta.

Para subir conjuntamente los cambios pendientes del Buzón y de este aviso, se preparó `only=actualizacion-buzon-listados` en el controlador temporal. La [guía de despliegue del 12 de noviembre](despliegue-buzon-listados-aviso-2026-11-12.md) detalla todos los archivos y el enlace de ejecución; incluye únicamente la migración de listados y las cachés, sin volver a instalar permisos.

Pruebas: `PeriodClosingNoticeTest`, `TwoFactorAuthenticationTest` y `period-closing-notice.test.cjs`. Las comprobaciones de fechas usan un reloj simulado con SQLite en memoria; no cambian la fecha real ni datos del servidor o de usuarios locales.

## Simulación manual para administradores

En **Configuración → Configuraciones → Mantenimiento**, el botón **Simular aviso de cierre** abre el mismo aviso y su botón **Entendido**, con una etiqueta explícita de simulación. La dirección autenticada es `/configuracion/mantenimiento/simular-cierre`.

La vista previa calcula una fecha de ejemplo cinco días antes del último día del **período configurado**: si es noviembre de 2026, muestra 25/11/2026 y el fin de mes 30/11/2026. No cambia el reloj ni fuerza la condición del aviso real. También puede previsualizarse un período cerrado: el mensaje de simulación aclara que no representa ni cambia su estado real.

Solo los administradores activos pueden abrirla, incluso mediante enlace directo. No usa el controlador temporal ni su token. No se escribe una marca de simulación en sesión, no se consumen avisos reales pendientes, no se envían notificaciones y no se alteran registros, cierres o bloqueos programados. Las tareas normales de la aplicación, como la bitácora de consultas, siguen funcionando. La respuesta lleva `Cache-Control: private, no-store`.

Cerrar con Entendido o Escape solo cierra la ventana. **Volver al mantenimiento** abre la página habitual sin simulación; navegar a otra página no repite la vista previa. Recargar la dirección de simulación o pulsar de nuevo el botón permite volver a probarla. Sin JavaScript se conserva el aviso visible como texto.

Para publicar esta simulación sobre la actualización ya instalada, subir estos seis archivos y renovar las cachés de rutas y vistas. **No requiere migraciones ni seeders.**

```text
app/Http/Controllers/SystemMaintenanceController.php
app/Support/PeriodClosingNotice.php
resources/views/maintenance/index.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/partials/period-closing-notice.blade.php
routes/web.php
```

Se reutilizan los CSS y JavaScript del aviso ya desplegados. Pruebas adicionales: `PeriodClosingPreviewTest`, junto con `PeriodClosingNoticeTest` y `SystemMaintenanceTest` para verificar la autorización, las fechas, el aislamiento de la simulación y los bloqueos existentes.
