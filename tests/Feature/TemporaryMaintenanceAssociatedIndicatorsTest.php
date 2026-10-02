<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenanceAssociatedIndicatorsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_30_120000_create_associated_indicators_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        // Never read real credentials or execute deployment commands in these tests.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;
            public string $token = 'test-associated-token';
            protected function maintenanceToken(): string { return $this->token; }
            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
        });
    }

    private function endpoint(array $parameters = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'token' => 'test-associated-token', 'only' => 'indicadores-asociados',
        ], $parameters));
    }

    public function test_only_associated_migration_and_caches_run_in_order_even_on_repeat(): void
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
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('INDICADORES ASOCIADOS:')
                ->assertSee('No se ejecutan seeders ni importaciones')
                ->assertSee('Repetir este modo conserva las asociaciones y copias guardadas.')
                ->assertSee('PERMISSION CACHE:');
        }
    }

    public function test_invalid_token_missing_token_and_mixed_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '0']))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_files_stop_before_any_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach ([self::MIGRATION, 'app/Models/IndicadorProyecto.php',
            'app/Http/Controllers/IndicadorProyectoController.php', 'app/Http/Controllers/ReportController.php',
            'app/Http/Requests/Concerns/ValidatesAssociatedIndicators.php',
            'app/Http/Requests/StoreReportRequest.php', 'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
            'resources/views/proyectos/indicadores/index.blade.php', 'resources/views/proyectos/indicadores/asociados.blade.php',
            'resources/views/reports/create.blade.php', 'public/js/associated-indicators.js',
            'public/css/indicator-select2.css', 'routes/web.php'] as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_migration_failure_stops_without_warming_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido');
    }

    public function test_migration_exception_is_reported_without_following_commands(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()->andThrow(new \RuntimeException('Fallo de prueba'));
        Artisan::shouldReceive('output')->times(2)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('ERROR: Fallo de prueba');
    }
}
