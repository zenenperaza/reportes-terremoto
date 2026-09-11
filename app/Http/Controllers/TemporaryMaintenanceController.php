<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TemporaryMaintenanceController extends Controller
{
    public function __invoke(): Response
    {
        $token = $this->maintenanceToken();

        abort_if($token === '', 503, 'Falta configurar SERVER_MAINTENANCE_TOKEN en el archivo .env.');
        abort_unless(hash_equals($token, (string) request('token')), 403);

        $cacheCommands = [
            ['name' => 'optimize:clear', 'parameters' => []],
            ['name' => 'cache:clear', 'parameters' => []],
        ];

        $migrationCommands = [
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_05_120000_create_donantes_proyectos_indicadores_tables.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_06_120000_link_users_projects_and_reports.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_06_130000_create_actividades_servicios_tables.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_07_090000_replace_indicator_population_with_age_range.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_07_100000_link_reports_to_activities_and_services.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_11_120000_create_system_settings_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_13_120000_create_project_location_tables.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_17_160000_add_profile_photo_to_users_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_17_170000_add_can_mark_reported_to_users_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_18_120000_create_sector_project_structure.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_20_120000_add_estatus_to_sectors_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_20_130000_create_user_groups_and_link_users.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_27_120000_add_informed_consent_to_beneficiaries_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_28_120000_add_nombre_alias_to_proyectos_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_02_115244_create_permission_tables.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_02_120000_sync_legacy_user_roles_with_spatie.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_02_121000_create_core_permissions.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_02_122000_add_report_management_permissions.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_03_130000_add_email_two_factor_authentication.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_04_120000_add_backup_management_permissions.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_04_130000_add_report_output_permissions.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_07_120000_remove_mark_reported_role_permission.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_10_120000_create_indicator_groups_and_link_indicators.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_10_130000_add_nombre_corto_to_indicadores_table.php',
                '--force' => true,
            ]],
            ['name' => 'db:seed', 'parameters' => [
                '--class' => 'Database\\Seeders\\IndicatorGroupSeeder',
                '--force' => true,
            ]],
            ['name' => 'db:seed', 'parameters' => [
                '--class' => 'Database\\Seeders\\ActividadSeeder',
                '--force' => true,
            ]],
            ['name' => 'db:seed', 'parameters' => [
                '--class' => 'Database\\Seeders\\ServicioSeeder',
                '--force' => true,
            ]],
            ['name' => 'backup:automatic', 'parameters' => []],
        ];

        $excelImportCommands = [
            ['name' => 'db:seed', 'parameters' => [
                '--class' => 'Database\\Seeders\\ImportarRegistrosExcelSeeder',
                '--force' => true,
            ]],
        ];

        $warmupCommands = [
            ['name' => 'config:cache', 'parameters' => []],
            ['name' => 'route:cache', 'parameters' => []],
            ['name' => 'view:cache', 'parameters' => []],
        ];

        $cacheOnly = request()->boolean('only_cache') || request('only') === 'cache';
        $excelOnly = request('only') === 'excel';
        $includeExcel = $excelOnly || request()->boolean('import_excel');

        $commands = array_merge(
            $cacheCommands,
            $cacheOnly || $excelOnly ? [] : $migrationCommands,
            $includeExcel ? $excelImportCommands : [],
            $warmupCommands,
        );

        $results = [];
        $exitCode = 1;

        try {
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command['name'], $command['parameters']);
                $results[] = strtoupper($command['name'])." (código {$exitCode}):";
                $results[] = trim(Artisan::output()) ?: 'Comando ejecutado sin mensajes.';

                if ($exitCode !== 0) {
                    $results[] = 'Proceso detenido porque el comando anterior no terminó correctamente.';
                    break;
                }
            }

            if ($exitCode === 0 && class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                $results[] = 'PERMISSION CACHE: caché de roles y permisos limpiada correctamente.';
            }

            return response(
                '<pre>'.e(implode("\n\n", $results)).'</pre>',
                $exitCode === 0 ? 200 : 500,
            );
        } catch (Throwable $exception) {
            return response('<pre>ERROR: '.e($exception->getMessage()).'</pre>', 500);
        }
    }

    private function maintenanceToken(): string
    {
        $environmentFile = base_path('.env');
        $contents = is_file($environmentFile) ? (string) file_get_contents($environmentFile) : '';

        if (preg_match('/^SERVER_MAINTENANCE_TOKEN=(.*)$/m', $contents, $matches) !== 1) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }
}
