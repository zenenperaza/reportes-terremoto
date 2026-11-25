<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemporaryMaintenanceSummaryProjectCodesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_24_120000_scope_summary_indicator_codes_to_projects.php';
    private const FILES = [self::MIGRATION, 'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/SummaryIndicatorController.php', 'app/Models/SummaryIndicator.php',
        'resources/views/summary-indicators/form.blade.php', 'routes/maintenance.php'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController {
            public string $token = 'test-summary-code-token';
            public ?string $missing = null;
            public ?string $stale = null;
            protected function maintenanceToken(): string { return $this->token; }
            protected function deploymentFileExists(string $file): bool { return $file !== $this->missing && parent::deploymentFileExists($file); }
            protected function deploymentFileContains(string $file, string $marker): bool { return $file !== $this->stale && parent::deploymentFileContains($file, $marker); }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace(['only' => 'codigos-resumen-por-proyecto', 'token' => 'test-summary-code-token'], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []], ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_one_targeted_migration_and_caches_run_even_when_repeated(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('CÓDIGOS DE INDICADORES RESUMEN POR PROYECTO:')
                ->assertSee('únicamente la nueva migración de índices')->assertSee('proyecto + código')
                ->assertSee('No se borran, renombran ni reasignan')->assertSee('No se ejecutan seeders')
                ->assertSee('No se activa ni se cancela el mantenimiento')->assertSee('PERMISSION CACHE:')
                ->assertDontSee('test-summary-code-token')->assertDontSee('DB:SEED');
        }
    }

    public function test_invalid_modes_credentials_and_mixed_options_execute_no_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get($this->endpoint(['token' => null]))->assertForbidden();
        $this->get($this->endpoint(['only' => 'codigos-resumen-por-proyecto-typo']))->assertStatus(422);
        $this->get($this->endpoint(['only' => null, 'nly' => 'codigos-resumen-por-proyecto']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_files_or_stale_form_and_controller_block_all_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        foreach (self::FILES as $file) {
            $controller->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
        $controller->missing = null;
        foreach (['app/Http/Controllers/SummaryIndicatorController.php', 'resources/views/summary-indicators/form.blade.php'] as $file) {
            $controller->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public function test_missing_previous_summary_schema_does_not_install_old_features(): void
    {
        Artisan::shouldReceive('call')->never();
        Schema::rename('summary_indicator_assignments', 'summary_assignments_unavailable');
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Este modo requiere Indicadores Resumen ya instalados con proyecto y asignaciones. No se ejecutaron comandos.');
    }

    public function test_schema_postconditions_detect_an_unapplied_index_change(): void
    {
        Schema::table('summary_indicators', fn ($table) => $table->unique('code', 'summary_indicators_code_unique'));
        foreach (self::commands() as [$name, $parameters]) Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        Artisan::shouldReceive('output')->times(6)->andReturn('Nothing to migrate');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('No se confirmó el índice único por proyecto')->assertDontSee('PERMISSION CACHE:');
        $this->assertTrue(Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique'));
    }

    public static function failures(): array
    {
        return array_map(fn ($index) => [$index], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_command_failures_stop_the_remaining_commands(int $failure): void
    {
        foreach (self::commands() as $stage => [$name, $parameters]) {
            if ($stage > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($stage === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Controlled failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }
}
