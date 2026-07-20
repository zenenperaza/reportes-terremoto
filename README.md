# Sistema de Respuesta UNICEF Venezuela - Terremoto

Aplicación Laravel para que los socios implementadores registren actividades de respuesta, beneficiarios, ubicación, notas de campo y medios de verificación.

## Incluye

- Registro e inicio de sesión para personas reportantes.
- Formulario basado en el reporte UNICEF: quién implementa, dónde, qué actividad se realizó, a quién llegó, necesidades específicas y cierre cualitativo.
- Desagregación de beneficiarios con validación: la suma debe coincidir exactamente con el total reportado.
- Carga privada de hasta tres evidencias por reporte (máximo 10 MB por archivo).
- Panel personal para reportantes y panel de coordinación para ver todos los registros, revisarlos y exportarlos a CSV.
- Ubicación dependiente Estado → Municipio → Parroquia.

El archivo de referencia entregado se conserva en `database/reference/unicef-terremoto-referencia.xlsx`. El seeder carga automáticamente 24 estados, 335 municipios y 1.135 parroquias, además de sectores y actividades iniciales.

## Puesta en marcha

```powershell
cd D:\Onedrive\www\unicef-terremoto
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Abra `http://127.0.0.1:8000`, cree la primera cuenta y registre una actividad.

La aplicación queda configurada para MySQL local en la base `registros`. Los parámetros se encuentran en `.env` (`UNICEF_DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`). Para desplegarla en otro entorno, actualice esos valores antes de ejecutar migraciones.

## Roles de coordinación

Todas las cuentas nuevas son reportantes. Después de crear una cuenta, promuévala desde la terminal para habilitar la vista de coordinación:

```powershell
php artisan reports:make-coordinator correo@organizacion.org
```

Use `--admin` si la cuenta debe tener rol de administrador.

## Verificación

```powershell
php artisan test
```
