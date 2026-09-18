# Excluir indicadores del Informe de beneficiarios

En Configuración > Indicadores, al crear o editar, el check **Excluir para reportes de beneficiarios** permite retirar los registros de ese indicador del Informe de beneficiarios.

- Inicialmente está desmarcado para todos los indicadores existentes.
- La exclusión afecta todas sus asignaciones a proyectos, los totales, agrupaciones, resumen 345W, Excel y la acción de marcar como reportado de ese informe.
- Se aplica tanto a pendientes como a registros ya reportados, sin cambiar sus fechas ni su estado almacenado.
- No elimina registros ni beneficiarios, no desactiva el indicador y no altera Registros de actividades ni Informes generales.
- Desmarcarlo vuelve a incluir los registros, sujetos a los filtros y permisos habituales.
- Los registros antiguos sin indicador de proyecto siguen incluidos.

## Despliegue

Subir estos archivos desde la raíz del proyecto:

1. `app/Models/Indicador.php`
2. `app/Http/Controllers/IndicadorController.php`
3. `app/Http/Controllers/BeneficiaryReportController.php`
4. `app/Http/Controllers/TemporaryMaintenanceController.php`
5. `resources/views/indicadores/form.blade.php`
6. `database/migrations/2026_09_18_120000_add_excluir_reporte_beneficiarios_to_indicadores_table.php`

La nueva columna debe existir antes de usar el código actualizado. Con consola:

```sh
php artisan migrate --path=database/migrations/2026_09_18_120000_add_excluir_reporte_beneficiarios_to_indicadores_table.php --force
php artisan view:clear
```

Sin consola, el controlador de mantenimiento incluye esta migración. Ejecutar el endpoint habitual `/ejecutar-migraciones-temp?token=TU_TOKEN` con el token del servidor, sin `only_cache=1` ni `only=cache` (esos modos no ejecutan migraciones). El endpoint también ejecuta las demás tareas de mantenimiento que ya tenía configuradas. No compartir el token.

## Verificación

```sh
php artisan test --compact --filter=IndicatorBeneficiaryExclusionTest
```

Verificar también en pantalla: activar el check en un indicador con registros, confirmar que desaparecen del Informe de beneficiarios y su Excel, y desmarcarlo para comprobar que vuelven a incluirse.
