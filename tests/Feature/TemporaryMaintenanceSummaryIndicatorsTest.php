<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceSummaryIndicatorsTest extends TestCase
{
    private const MIGRATIONS = [
        'database/migrations/2026_10_26_140000_create_summary_indicators_tables.php',
        'database/migrations/2026_10_26_150000_scope_summary_indicators_to_projects.php',
    ];

    private const REQUIRED_FILES = [
        ...self::MIGRATIONS,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/SummaryIndicatorController.php',
        'app/Http/Controllers/BeneficiaryReportController.php',
        'app/Http/Controllers/GeneralReportController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Controllers/ServicioProgramadoController.php',
        'app/Http/Controllers/ProyectoController.php',
        'app/Models/SummaryIndicator.php', 'app/Models/Proyecto.php',
        'app/Support/SummaryIndicatorFilter.php',
        'app/Services/ReportSummaryExcelExport.php',
        'app/Services/ProgrammedServicesExcelExport.php',
        'resources/views/summary-indicators/index.blade.php',
        'resources/views/summary-indicators/form.blade.php',
        'resources/views/summary-indicators/assignments.blade.php',
        'resources/views/reports/partials/summary-indicator-filter.blade.php',
        'resources/views/reports/index.blade.php',
        'resources/views/beneficiaries/summary.blade.php',
        'resources/views/general-reports/partials/filters-and-summary.blade.php',
        'resources/views/servicios/programados.blade.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/partials/configuration-menu.blade.php',
        'public/js/summary-indicator-filter.js',
        'routes/web.php', 'routes/maintenance.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Never read deployment credentials or execute real Artisan deployment commands.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;
            public string $token = 'test-summary-indicators-token';

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
            'only' => 'indicadores-resumen', 'token' => 'test-summary-indicators-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [
            ['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATIONS[0], '--force' => true]],
            ['migrate', ['--path' => self::MIGRATIONS[1], '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []],
        ];
    }

    public function test_only_summary_migrations_run_in_order_and_repeated_requests_preserve_configuration(): void
    {
        for ($run = 0; $run < 2; $run++) {
            foreach (self::commands() as [$command, $parameters]) {
                Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
            }
        }
        Artisan::shouldReceive('output')->times(14)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()
                ->assertSee('INDICADORES RESUMEN POR PROYECTO:')
                ->assertSee('No se crean asignaciones automáticas ni se deducen proyectos de pruebas anteriores.')
                ->assertSee('proyecto pendiente hasta confirmarlo en Editar')
                ->assertSee('No se ejecutan seeders, importaciones ni correcciones de parroquias.')
                ->assertSee('No se modifica el formulario /reportes/nuevo.')
                ->assertSee('Las migraciones ya aplicadas se omiten.')
                ->assertSee('se conservan los bloqueos y avisos programados.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-summary-indicators-token');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_invalid_tokens_mode_and_conflicting_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=indicadores-resumen')->assertForbidden();
        $this->get($this->endpoint(['only' => 'indicadores-resumen-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $field) {
            foreach (['0', '1', ''] as $value) {
                $this->get($this->endpoint([$field => $value]))->assertStatus(422);
            }
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_files_stop_before_any_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::REQUIRED_FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public static function failureStages(): array
    {
        return array_map(fn (int $stage) => [$stage], array_keys(self::commands()));
    }

    #[DataProvider('failureStages')]
    public function test_failure_at_any_stage_stops_subsequent_commands(int $failureStage): void
    {
        foreach (self::commands() as $stage => [$command, $parameters]) {
            if ($stage > $failureStage) break;
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()
                ->andReturn($stage === $failureStage ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failureStage + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_exception_during_second_migration_is_escaped_and_stops_cache_warmup(): void
    {
        foreach (array_slice(self::commands(), 0, 3) as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATIONS[1], '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<fallo de prueba>'));
        Artisan::shouldReceive('output')->times(3)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo de prueba&gt;', false)
            ->assertDontSee('<fallo de prueba>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
