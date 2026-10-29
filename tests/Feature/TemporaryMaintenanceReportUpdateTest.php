<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceReportUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;

            public string $token = 'test-report-update-token';

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

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'only' => 'actualizacion-informes', 'token' => 'test-report-update-token',
        ], $options));
    }

    public function test_runs_only_cache_commands_on_repeat_without_migrations_seeders_or_data_corrections(): void
    {
        for ($run = 0; $run < 2; $run++) {
            foreach (['optimize:clear', 'cache:clear', 'config:cache', 'route:cache', 'view:cache'] as $command) {
                Artisan::shouldReceive('call')->once()->with($command, [])->ordered()->andReturn(0);
            }
        }
        Artisan::shouldReceive('output')->times(10)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()
                ->assertSee('ACTUALIZACIÓN DE INFORMES, PANEL Y REGISTROS:')
                ->assertSee('Proyectos aparece primero')
                ->assertSee('respetando los permisos existentes')
                ->assertSee('No se ejecutan migraciones, seeders ni importaciones.')
                ->assertSee('ya fue corregida manualmente en el servidor y no se modifica.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-report-update-token');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_invalid_tokens_unknown_mode_and_conflicting_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-informes')->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-informes-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $field) {
            foreach (['0', '1', ''] as $value) {
                $this->get($this->endpoint([$field => $value]))->assertStatus(422);
            }
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_incomplete_upload_aborts_before_clearing_caches(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach ([
            'app/Http/Controllers/TemporaryMaintenanceController.php',
            'app/Http/Controllers/BeneficiaryReportController.php',
            'app/Http/Controllers/DashboardController.php',
            'app/Http/Controllers/ReportController.php',
            'app/Http/Requests/StoreReportRequest.php',
            'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
            'app/Services/ReportSummaryExcelExport.php',
            'app/Services/ProgrammedServicesExcelExport.php',
            'app/Support/ReportPeriod.php', 'app/Support/ReportPeriodDates.php',
            'app/Support/SummaryIndicatorFilter.php', 'app/Services/ReportLocationOptions.php',
            'public/css/beneficiary-summary.css',
            'public/js/beneficiary-project-filter.js', 'public/js/beneficiary-indicator-picker.js',
            'public/js/report-period-dates.js', 'public/js/period-filter.js',
            'public/js/summary-indicator-filter.js',
            'public/css/report-excel-button.css', 'public/css/programmed-services.css',
            'public/js/general-report-locations.js', 'public/js/service-report-table.js',
            'resources/views/dashboard.blade.php',
            'resources/views/general-reports/index.blade.php',
            'resources/views/general-reports/partials/excel-export.blade.php',
            'resources/views/general-reports/partials/filters-and-summary.blade.php',
            'resources/views/indicator-reports/index.blade.php',
            'resources/views/beneficiaries/summary.blade.php',
            'resources/views/beneficiaries/partials/indicator-picker.blade.php',
            'resources/views/reports/partials/period-filter.blade.php',
            'resources/views/reports/partials/period-date-assets.blade.php',
            'resources/views/reports/partials/period-date-error.blade.php',
            'resources/views/reports/partials/summary-indicator-filter.blade.php',
            'resources/views/reports/create.blade.php',
            'resources/views/servicios/programados.blade.php',
            'routes/web.php', 'routes/maintenance.php',
        ] as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_failure_stops_the_command_sequence(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(2)->andReturn('Failed');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_exceptions_are_escaped_and_prevent_subsequent_commands(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()
            ->andThrow(new RuntimeException('<fallo de prueba>'));
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo de prueba&gt;', false)
            ->assertDontSee('<fallo de prueba>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
