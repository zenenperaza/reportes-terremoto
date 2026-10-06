<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenanceServiceReportsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_06_120000_add_service_report_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        // No real token reads or deployment commands; commands are mocked below.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;

            public string $token = 'test-services-token';

            protected function maintenanceToken(): string
            {
                return $this->token;
            }

            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
        });
    }

    private function endpoint(array $parameters = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'token' => 'test-services-token', 'only' => 'informes-servicios',
        ], $parameters));
    }

    public function test_only_service_permissions_and_caches_run_in_order_even_on_repeat(): void
    {
        for ($run = 0; $run < 2; $run++) {
            foreach ([
                ['optimize:clear', []], ['cache:clear', []],
                ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
                ['config:cache', []], ['route:cache', []], ['view:cache', []],
            ] as [$command, $parameters]) {
                Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
            }
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('Nothing to migrate.');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('INFORMES POR SERVICIOS:')
                ->assertSee('No se ejecutan seeders ni importaciones.')
                ->assertSee('Repetir este modo conserva los permisos y asignaciones guardados.')
                ->assertSee('PERMISSION CACHE:');
        }
    }

    public function test_authentication_and_conflicting_modes_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get($this->endpoint(['token' => '']))->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '0']))->assertStatus(422);
        }
        $this->get($this->endpoint(['only' => 'informes-servicio']))->assertStatus(422);
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_incomplete_upload_stops_before_any_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach ([
            self::MIGRATION, 'app/Http/Controllers/ServicioProgramadoController.php',
            'app/Services/ProgrammedServicesExcelExport.php', 'app/Http/Controllers/PermissionController.php',
            'app/Http/Controllers/ReportController.php', 'app/Models/Report.php', 'app/Models/ServicioActividad.php',
            'resources/views/servicios/programados.blade.php', 'resources/views/servicios/index.blade.php',
            'resources/views/layouts/app.blade.php', 'resources/views/reports/index.blade.php',
            'resources/views/reports/partials/period-filter.blade.php', 'public/css/programmed-services.css',
            'public/css/report-datatable.css', 'public/js/service-report-table.js', 'public/js/period-filter.js',
            'public/vendor/datatables/dataTables.min.js', 'public/vendor/datatables/dataTables.dataTables.min.css',
            'public/vendor/datatables/dataTables.responsive.min.js', 'public/vendor/datatables/responsive.dataTables.min.css',
            'routes/web.php',
        ] as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_migration_failure_stops_without_warming_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_exceptions_are_reported_without_following_commands(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new \RuntimeException('<fallo de prueba>'));
        Artisan::shouldReceive('output')->times(2)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('ERROR: &lt;fallo de prueba&gt;', false)
            ->assertDontSee('PERMISSION CACHE:');
    }
}
