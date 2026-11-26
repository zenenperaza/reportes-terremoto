<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use App\Models\{Donante, Indicador, IndicadorProyecto, Proyecto};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceProjectIndicatorExclusionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_26_120000_scope_beneficiary_exclusion_to_project_indicators.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController {
            public string $token = 'test-project-exclusion-token';
            public ?string $missing = null;
            public ?string $stale = null;
            public bool $oldRuntime = false;
            public bool $oldCatalogCheckbox = false;
            public function files(): array { return $this->projectIndicatorExclusionFiles(); }
            public function markers(): array { return $this->projectIndicatorExclusionMarkers(); }
            protected function maintenanceToken(): string { return $this->token; }
            protected function deploymentFileExists(string $file): bool { return $file !== $this->missing && parent::deploymentFileExists($file); }
            protected function deploymentFileContains(string $file, string $marker): bool
            {
                if ($this->oldCatalogCheckbox && $file === 'resources/views/indicadores/form.blade.php' && $marker === 'name="excluir_reporte_beneficiarios"') return true;
                return $file !== $this->stale && parent::deploymentFileContains($file, $marker);
            }
            protected function projectIndicatorExclusionRuntimeAvailable(): bool { return ! $this->oldRuntime && parent::projectIndicatorExclusionRuntimeAvailable(); }
        });
    }

    private function endpoint(array $extra = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'exclusion-indicador-por-proyecto', 'token' => 'test-project-exclusion-token',
        ], $extra));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []], ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_the_targeted_migration_and_caches_run_and_repeating_preserves_project_decisions(): void
    {
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-TEST', 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
        $indicator = Indicador::create(['codigo' => 'IND-TEST', 'descripcion' => 'Indicador de prueba', 'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120]);
        $indicator->forceFill(['excluir_reporte_beneficiarios' => true])->save();
        $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'indicador_id' => $indicator->id,
            'estatus' => true, 'meta_cuantitativa' => 20, 'excluir_reporte_beneficiarios' => false]);
        $before = $assignment->fresh()->getAttributes();
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('Nothing to migrate');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('EXCLUSIÓN DE INDICADORES POR PROYECTO Y AJUSTES DE FORMULARIOS:')
                ->assertSee('únicamente la nueva migración de exclusión por proyecto')
                ->assertSee('incluidas las inactivas')->assertSee('no restaura decisiones modificadas posteriormente.')
                ->assertSee('Desde y Hasta pueden ser iguales')->assertSee('Grupo de edad primero')
                ->assertSee('No se ejecutan seeders')->assertSee('No se activa ni se cancela el mantenimiento')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('DB:SEED')->assertDontSee('test-project-exclusion-token')
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame($before, $assignment->fresh()->getAttributes());
            $this->assertTrue($indicator->fresh()->excluir_reporte_beneficiarios);
        }
    }

    public function test_invalid_credentials_modes_and_mixed_options_run_no_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get($this->endpoint(['token' => null]))->assertForbidden();
        $this->get($this->endpoint(['only' => 'exclusion-indicador-por-proyecto-typo']))->assertStatus(422);
        $this->get($this->endpoint(['only' => null, 'nly' => 'exclusion-indicador-por-proyecto']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_files_and_stale_sources_block_all_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        foreach ($controller->files() as $file) {
            $controller->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
        $controller->missing = null;
        foreach (array_keys($controller->markers()) as $file) {
            $controller->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public function test_old_catalog_checkbox_models_or_buzon_order_block_all_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        $controller->oldCatalogCheckbox = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba el formulario de Indicadores actualizado: la exclusión ya no se configura en el catálogo general.');
        $controller->oldCatalogCheckbox = false;
        $controller->oldRuntime = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba los modelos actualizados y los formularios con Grupo de edad primero. No se ejecutaron comandos.');
    }

    public function test_previous_features_are_required_without_running_other_migrations(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (['indicadores', 'indicador_proyecto', 'buzon_entries'] as $table) {
            Schema::rename($table, $table.'_unavailable');
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
                'Este modo requiere los indicadores por proyecto y el grupo de edad del Buzón ya instalados. No se ejecutaron comandos.');
            Schema::rename($table.'_unavailable', $table);
        }
    }

    public function test_an_unapplied_migration_is_reported_without_implicit_repairs(): void
    {
        Schema::table('indicador_proyecto', fn ($table) => $table->dropColumn('excluir_reporte_beneficiarios'));
        foreach (self::commands() as [$name, $parameters]) Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        Artisan::shouldReceive('output')->times(6)->andReturn('Nothing to migrate');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('No se confirmó la exclusión por proyecto.')
            ->assertDontSee('PERMISSION CACHE:');
    }

    public static function failures(): array
    {
        return array_map(fn ($index) => [$index], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_command_failure_stops_remaining_steps(int $failure): void
    {
        foreach (self::commands() as $index => [$name, $parameters]) {
            if ($index > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($index === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_command_exception_is_escaped_and_stops_the_process(): void
    {
        foreach (array_slice(self::commands(), 0, 2) as [$name, $parameters]) Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        Artisan::shouldReceive('output')->twice()->andReturn('OK');
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<fallo controlado>'));
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo controlado&gt;', false)
            ->assertDontSee('<fallo controlado>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
