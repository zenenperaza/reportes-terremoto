<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class TemporaryMaintenanceController extends Controller
{
    public function __invoke(): Response
    {
        $token = $this->maintenanceToken();

        abort_if($token === '', 503, 'Falta configurar SERVER_MAINTENANCE_TOKEN en el archivo .env.');
        abort_unless(hash_equals($token, (string) request('token')), 403);

        abort_unless(in_array(request('only'), [null, '', 'cache', 'excel', 'permisos-beneficiarios', 'edicion-cruzada-grupos', 'periodos', 'indicadores-asociados', 'informes-servicios', 'mantenimiento-en-vivo', 'notificaciones', 'actualizacion-sia'], true),
            422, 'Modo de mantenimiento no reconocido.');
        $permissionsOnly = request('only') === 'permisos-beneficiarios';
        $groupEditingOnly = request('only') === 'edicion-cruzada-grupos';
        $periodsOnly = request('only') === 'periodos';
        $associatedIndicatorsOnly = request('only') === 'indicadores-asociados';
        $serviceReportsOnly = request('only') === 'informes-servicios';
        $liveMaintenanceOnly = request('only') === 'mantenimiento-en-vivo';
        $notificationsOnly = request('only') === 'notificaciones';
        $siaUpdateOnly = request('only') === 'actualizacion-sia';
        abort_if($siaUpdateOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo actualizacion-sia no se puede combinar con opciones de caché o importación.');
        abort_if($notificationsOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo notificaciones no se puede combinar con opciones de caché o importación.');
        abort_if($liveMaintenanceOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo mantenimiento-en-vivo no se puede combinar con opciones de caché o importación.');
        abort_if($serviceReportsOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo informes-servicios no se puede combinar con opciones de caché o importación.');
        abort_if($associatedIndicatorsOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo indicadores-asociados no se puede combinar con opciones de caché o importación.');
        abort_if($periodsOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo periodos no se puede combinar con opciones de caché o importación.');
        abort_if($permissionsOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo permisos-beneficiarios no se puede combinar con opciones de caché o importación.');
        abort_if($groupEditingOnly && request()->hasAny(['only_cache', 'import_excel', 'apply', 'decisions']),
            422, 'El modo edicion-cruzada-grupos no se puede combinar con opciones de caché o importación.');

        $permissionMigration = ['name' => 'migrate', 'parameters' => [
            '--path' => 'database/migrations/2026_09_23_180000_add_beneficiary_management_permissions.php',
            '--force' => true,
        ]];
        abort_if($permissionsOnly && ! is_file(base_path($permissionMigration['parameters']['--path'])),
            422, 'Suba la migración 2026_09_23_180000_add_beneficiary_management_permissions.php antes de continuar.');

        $groupEditingMigration = ['name' => 'migrate', 'parameters' => [
            '--path' => 'database/migrations/2026_09_23_160539_add_allow_member_editing_to_user_groups_table.php',
            '--force' => true,
        ]];
        abort_if($groupEditingOnly && ! is_file(base_path($groupEditingMigration['parameters']['--path'])),
            422, 'Suba la migración 2026_09_23_160539_add_allow_member_editing_to_user_groups_table.php antes de continuar.');

        $periodMigrations = array_map(fn (string $path): array => [
            'name' => 'migrate', 'parameters' => ['--path' => $path, '--force' => true],
        ], [
            'database/migrations/2026_09_28_120000_add_reporting_period_to_reports.php',
            'database/migrations/2026_09_28_130000_create_reporting_periods_table.php',
        ]);
        if ($periodsOnly) {
            $requiredFiles = array_merge(array_column(array_column($periodMigrations, 'parameters'), '--path'), [
                'app/Models/ReportingPeriod.php',
                'app/Support/ReportPeriod.php',
                'app/Http/Middleware/ReportPeriodTransaction.php',
                'resources/views/reports/partials/period-filter.blade.php',
                'public/css/system-configuration.css',
                'public/js/navigation-disclosure.js',
                'public/js/period-filter.js',
            ]);
            foreach ($requiredFiles as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
        }

        $associatedIndicatorMigration = ['name' => 'migrate', 'parameters' => [
            '--path' => 'database/migrations/2026_09_30_120000_create_associated_indicators_tables.php',
            '--force' => true,
        ]];
        if ($associatedIndicatorsOnly) {
            foreach ([
                $associatedIndicatorMigration['parameters']['--path'],
                'app/Models/IndicadorProyecto.php',
                'app/Http/Controllers/IndicadorProyectoController.php',
                'app/Http/Controllers/ReportController.php',
                'app/Http/Requests/Concerns/ValidatesAssociatedIndicators.php',
                'app/Http/Requests/StoreReportRequest.php',
                'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
                'resources/views/proyectos/indicadores/index.blade.php',
                'resources/views/proyectos/indicadores/asociados.blade.php',
                'resources/views/reports/create.blade.php',
                'public/js/associated-indicators.js',
                'public/css/indicator-select2.css',
                'routes/web.php',
            ] as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
        }

        $serviceReportMigration = ['name' => 'migrate', 'parameters' => [
            '--path' => 'database/migrations/2026_10_06_120000_add_service_report_permissions.php',
            '--force' => true,
        ]];
        if ($serviceReportsOnly) {
            foreach ([
                $serviceReportMigration['parameters']['--path'],
                'app/Http/Controllers/ServicioProgramadoController.php',
                'app/Services/ProgrammedServicesExcelExport.php',
                'app/Http/Controllers/PermissionController.php',
                'app/Http/Controllers/ReportController.php',
                'app/Models/Report.php',
                'app/Models/ServicioActividad.php',
                'app/Support/ReportPeriodDates.php',
                'resources/views/servicios/programados.blade.php',
                'resources/views/servicios/partials/entregas.blade.php',
                'resources/views/servicios/index.blade.php',
                'resources/views/layouts/app.blade.php',
                'resources/views/reports/index.blade.php',
                'resources/views/reports/partials/period-filter.blade.php',
                'resources/views/reports/partials/period-date-assets.blade.php',
                'resources/views/reports/partials/period-date-error.blade.php',
                'public/css/programmed-services.css',
                'public/css/report-datatable.css',
                'public/js/service-report-table.js',
                'public/js/report-period-dates.js',
                'public/assets/libs/flatpickr/flatpickr.min.js',
                'public/assets/libs/flatpickr/flatpickr.min.css',
                'public/assets/libs/flatpickr/l10n/es.js',
                'public/js/period-filter.js',
                'public/vendor/datatables/dataTables.min.js',
                'public/vendor/datatables/dataTables.dataTables.min.css',
                'public/vendor/datatables/dataTables.responsive.min.js',
                'public/vendor/datatables/responsive.dataTables.min.css',
                'routes/web.php',
            ] as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
            abort_unless(class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class), 422,
                'Falta PhpSpreadsheet. Instale las dependencias de Composer del proyecto antes de continuar.');
        }

        $presenceMigration = ['name' => 'migrate', 'parameters' => [
            '--path' => 'database/migrations/2026_10_08_120000_create_user_presences_table.php', '--force' => true,
        ]];
        $notificationMigrations = array_map(fn ($path) => ['name' => 'migrate', 'parameters' => ['--path' => $path, '--force' => true]], [
            'database/migrations/2026_10_09_120000_create_system_notifications_table.php',
            'database/migrations/2026_10_09_120100_add_receive_notifications_permission.php',
        ]);
        if ($notificationsOnly || $siaUpdateOnly) {
            foreach (array_merge(array_column(array_column($notificationMigrations, 'parameters'), '--path'), [
                'app/Models/SystemNotification.php', 'app/Support/SystemNotifications.php', 'app/Models/User.php',
                'app/Http/Controllers/AuthController.php', 'app/Http/Controllers/ReportController.php',
                'app/Http/Controllers/SystemMaintenanceController.php', 'app/Http/Controllers/PermissionController.php',
                'resources/views/layouts/app.blade.php', 'resources/views/layouts/partials/desktop-notifications.blade.php',
                'public/js/desktop-notifications.js', 'public/js/system-live-maintenance.js', 'routes/web.php',
                'public/js/pwa.js', 'public/service-worker.js',
            ]) as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
        }
        if ($liveMaintenanceOnly || $siaUpdateOnly) {
            foreach ([
                $presenceMigration['parameters']['--path'],
                'app/Models/UserPresence.php', 'app/Models/SystemSetting.php',
                'app/Models/User.php', 'app/Models/SystemNotification.php', 'app/Support/SystemNotifications.php',
                'app/Http/Controllers/SystemMaintenanceController.php', 'app/Http/Controllers/AuthController.php',
                'resources/views/maintenance/index.blade.php', 'resources/views/maintenance/live-notice.blade.php',
                'resources/views/layouts/app.blade.php', 'public/css/system-maintenance.css',
                'resources/views/layouts/partials/desktop-notifications.blade.php', 'public/js/desktop-notifications.js',
                'public/css/maintenance-banner.css', 'public/js/system-live-maintenance.js', 'routes/web.php',
            ] as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
        }
        if ($siaUpdateOnly) {
            // Complete the preflight before clearing caches or running migrations.
            // These are the other runtime files in the pending SIA deployment.
            foreach ([
                'app/Http/Controllers/TemporaryMaintenanceController.php', 'routes/maintenance.php',
                'resources/views/layouts/partials/configuration-menu.blade.php',
                'resources/views/reports/create.blade.php', 'resources/views/reports/show.blade.php',
                'resources/views/beneficiaries/summary.blade.php', 'public/js/report-export.js',
            ] as $file) {
                abort_unless($this->deploymentFileExists($file), 422, 'Suba el archivo '.$file.' antes de continuar.');
            }
        }

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
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_11_120000_create_user_group_user_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_15_120000_create_audit_logs_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_16_150000_create_case_management_tables.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_16_170000_extend_case_forms_and_create_families.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_18_120000_add_excluir_reporte_beneficiarios_to_indicadores_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_28_120000_add_reporting_period_to_reports.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_09_28_130000_create_reporting_periods_table.php',
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

        $selectedMigrations = match (true) {
            $siaUpdateOnly => array_merge([$presenceMigration], $notificationMigrations),
            $notificationsOnly => $notificationMigrations,
            $liveMaintenanceOnly => [$presenceMigration],
            $serviceReportsOnly => [$serviceReportMigration],
            $associatedIndicatorsOnly => [$associatedIndicatorMigration],
            $periodsOnly => $periodMigrations,
            $permissionsOnly => [$permissionMigration],
            $groupEditingOnly => [$groupEditingMigration],
            $cacheOnly || $excelOnly => [],
            default => array_merge($migrationCommands, [$permissionMigration]),
        };
        $commands = array_merge(
            $cacheCommands,
            $selectedMigrations,
            $includeExcel ? $excelImportCommands : [],
            $warmupCommands,
        );

        $results = $permissionsOnly ? [
            'PERMISOS DE BENEFICIARIOS: se ejecutará únicamente la nueva migración de permisos y se reconstruirán las cachés. No se importarán ni modificarán registros o beneficiarios.',
            'Se conservan editar registros y eliminar registros. Se crean editar beneficiarios y eliminar beneficiarios heredando inicialmente las asignaciones correspondientes.',
        ] : ($groupEditingOnly ? [
            'EDICIÓN CRUZADA DE GRUPOS: se ejecutará únicamente la migración que agrega la columna allow_member_editing a user_groups y se reconstruirán las cachés. No se importarán ni modificarán registros o beneficiarios.',
            'Luego de aplicar esta migración, active "Edición cruzada entre miembros" en el grupo de usuarios correspondiente para permitir que los coordinadores editen registros de sus compañeros de grupo.',
        ] : []);
        if ($siaUpdateOnly) {
            $results = [
                'ACTUALIZACIÓN SIA: se ejecutan únicamente las tres migraciones de presencia y notificaciones, en orden, y se reconstruyen las cachés.',
                'Se crean user_presences, system_notifications y el permiso Recibir notificaciones, asignado inicialmente a admin, coordinator y reporter.',
                'No se ejecutan seeders ni importaciones. No se modifican registros, beneficiarios, equipos, servicios entregados, períodos ni cierres.',
                'No se activa ni se cancela el mantenimiento: se conservan los bloqueos y avisos programados.',
                'Las migraciones ya aplicadas se omiten. Repetir este modo conserva los permisos retirados y los avisos existentes.',
                'Al finalizar, recargue SIA con Ctrl + F5 para cargar el menú, el footer y las notificaciones actualizados.',
            ];
        }
        if ($notificationsOnly) {
            $results = [
                'NOTIFICACIONES: se ejecutan únicamente las dos migraciones de notificaciones y se reconstruyen las cachés.',
                'Se crea la bandeja de avisos y el permiso Recibir notificaciones, asignado inicialmente a admin, coordinator y reporter.',
                'No se ejecutan seeders ni importaciones; no se modifican registros, beneficiarios, equipos ni bloqueos.',
            ];
        }
        if ($liveMaintenanceOnly) {
            $results = [
                'MANTENIMIENTO EN VIVO: se crea únicamente la tabla de presencia y se reconstruyen las cachés.',
                'No se ejecutan seeders ni importaciones. No se modifican registros, beneficiarios, permisos ni el estado de bloqueo.',
                'Repetir este modo conserva los bloqueos y avisos programados.',
            ];
        }
        if ($periodsOnly) {
            $results = [
                'PERÍODOS: se ejecutarán únicamente las dos migraciones de períodos, en orden, y se reconstruirán las cachés.',
                'No se ejecutan seeders ni importaciones. No se asignan períodos a registros anteriores ni se cierra ningún período.',
                'Las migraciones ya aplicadas se omiten; repetir este modo conserva los períodos y cierres guardados.',
            ];
        }
        if ($associatedIndicatorsOnly) {
            $results = [
                'INDICADORES ASOCIADOS: se ejecutará únicamente la migración de indicadores asociados y se reconstruirán las cachés.',
                'Se crean las tablas indicador_proyecto_asociados y report_indicator_copies. No se ejecutan seeders ni importaciones, ni se duplican registros durante este mantenimiento.',
                'No se modifican registros, beneficiarios, períodos ni cierres existentes. Las asociaciones se configuran desde Indicadores asociados → Gestionar.',
                'Si la migración ya fue aplicada, se omite. Repetir este modo conserva las asociaciones y copias guardadas.',
            ];
        }
        if ($serviceReportsOnly) {
            $results = [
                'INFORMES POR SERVICIOS: se ejecutará únicamente la migración de permisos de informes por servicios y se reconstruirán las cachés.',
                'Se crean ver informes por servicios y exportar informes por servicios excel, asignados inicialmente al rol Administrador.',
                'No se ejecutan seeders ni importaciones. No se modifican registros, beneficiarios, servicios entregados, períodos ni cierres.',
                'Si la migración ya fue aplicada, se omite. Repetir este modo conserva los permisos y asignaciones guardados.',
                'La consulta muestra servicios seleccionados en registros con beneficiarios. No se infieren entregas ni cantidades a partir de actividades.',
            ];
        }
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

            if ($exitCode === 0 && class_exists(PermissionRegistrar::class)) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $results[] = 'PERMISSION CACHE: caché de roles y permisos limpiada correctamente.';
            }

            return response(
                '<pre>'.e(implode("\n\n", $results)).'</pre>',
                $exitCode === 0 ? 200 : 500,
            )->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            return response('<pre>ERROR: '.e($exception->getMessage()).'</pre>', 500)->header('Cache-Control', 'no-store');
        }
    }

    protected function deploymentFileExists(string $path): bool
    {
        return is_file(base_path($path))
            || (str_starts_with($path, 'public/') && is_file(public_path(substr($path, 7))));
    }

    protected function maintenanceToken(): string
    {
        $environmentFile = base_path('.env');
        $contents = is_file($environmentFile) ? (string) file_get_contents($environmentFile) : '';

        if (preg_match('/^SERVER_MAINTENANCE_TOKEN=(.*)$/m', $contents, $matches) !== 1) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }
}
