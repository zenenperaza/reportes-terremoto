<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceProjectPlacesTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_22_120000_create_project_place_assignments_table.php';

    private const REQUIRED_FILES = [
        self::MIGRATION,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/ProyectoController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Requests/StoreReportRequest.php',
        'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
        'app/Http/Requests/Concerns/ValidatesProjectPlaces.php',
        'app/Http/Requests/Concerns/PreservesReportLocation.php',
        'app/Models/Proyecto.php', 'app/Models/PlaceName.php',
        'resources/views/proyectos/create.blade.php',
        'resources/views/proyectos/edit.blade.php',
        'resources/views/proyectos/form.blade.php',
        'resources/views/proyectos/show.blade.php',
        'resources/views/reports/create.blade.php',
        'public/js/associated-indicators.js',
        'routes/web.php', 'routes/maintenance.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // No real credentials are read and no deployment command is executed.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;

            public string $token = 'test-project-places-token';

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
            'only' => 'lugares-proyecto', 'token' => 'test-project-places-token',
        ], $options));
    }

    public function test_only_project_places_migration_and_caches_run_in_order_on_repeated_requests(): void
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
            $response = $this->get($this->endpoint())->assertOk()->assertSee('LUGARES POR PROYECTO:')
                ->assertSee('No se ejecutan seeders, importaciones ni correcciones de parroquias.')
                ->assertSee('No se asignan lugares automáticamente.')
                ->assertSee('conserva las asignaciones guardadas.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-project-places-token');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_invalid_tokens_unknown_mode_and_conflicting_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=lugares-proyecto')->assertForbidden();
        $this->get($this->endpoint(['only' => 'lugares-proyecto-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $field) {
            foreach (['0', '1', ''] as $value) {
                $this->get($this->endpoint([$field => $value]))->assertStatus(422);
            }
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_runtime_files_stop_before_any_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::REQUIRED_FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_cache_failure_stops_before_migration(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(2)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_migration_failure_stops_without_warming_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_exceptions_are_escaped_and_prevent_subsequent_commands(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<fallo de prueba>'));
        Artisan::shouldReceive('output')->times(2)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo de prueba&gt;', false)
            ->assertDontSee('<fallo de prueba>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
