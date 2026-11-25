<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemporaryMaintenanceBuzonAgeGroupTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_25_120000_add_age_group_to_buzon_entries.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController {
            public string $token = 'test-age-group-token';
            public ?string $missing = null;
            public ?string $stale = null;
            public function files(): array { return $this->buzonAgeGroupFiles(); }
            protected function maintenanceToken(): string { return $this->token; }
            protected function deploymentFileExists(string $file): bool { return $file !== $this->missing && parent::deploymentFileExists($file); }
            protected function deploymentFileContains(string $file, string $marker): bool { return $file !== $this->stale && parent::deploymentFileContains($file, $marker); }
        });
    }

    private function endpoint(array $extra = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace(['only' => 'buzon-grupo-edad', 'token' => 'test-age-group-token'], $extra));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []], ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_the_new_migration_and_caches_run_and_repeating_is_safe(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('GRUPO DE EDAD DEL BUZÓN:')
                ->assertSee('únicamente la migración de age_group')->assertSee('Los registros históricos conservan sus datos')
                ->assertSee('fecha de nacimiento manual')->assertSee('DataTable de Consultar listados')
                ->assertSee('este modo no repite su migración ni rellena fechas históricas.')
                ->assertSee('No se ejecutan todas las migraciones pendientes.')
                ->assertSee('No se ejecutan seeders, importaciones ni otras migraciones.')
                ->assertSee('No se activa ni se cancela el mantenimiento')->assertSee('PERMISSION CACHE:')
                ->assertDontSee('test-age-group-token')->assertDontSee('DB:SEED');
        }
    }

    public function test_missing_credentials_unknown_mode_and_mixed_options_run_no_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get($this->endpoint(['token' => null]))->assertForbidden();
        $this->get($this->endpoint(['only' => 'buzon-grupo-edad-typo']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_or_stale_files_stop_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        foreach ($controller->files() as $file) {
            $controller->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
        $controller->missing = null;
        foreach (['app/Models/BuzonEntry.php', 'app/Http/Requests/SaveBuzonEntryRequest.php',
            'app/Http/Requests/SavePublicBuzonEntryRequest.php', 'app/Http/Controllers/BuzonController.php',
            'app/Http/Controllers/PublicBuzonController.php', 'app/Services/BuzonDataTable.php',
            'resources/views/buzon/form.blade.php', 'resources/views/buzon/public/form.blade.php',
            'resources/views/buzon/partials/entries.blade.php', 'public/js/buzon-table.js',
            'resources/views/reports/create.blade.php', 'public/js/beneficiary-birth-date-input.js',
            'public/js/beneficiary-id-autofill.js', 'resources/views/buzon/lists/index.blade.php',
            'public/js/buzon-lists-table.js', 'app/Services/BuzonListsDataTable.php'] as $file) {
            $controller->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public function test_previous_buzon_schema_is_required_without_installing_old_features(): void
    {
        Artisan::shouldReceive('call')->never();
        Schema::rename('buzon_lists', 'buzon_lists_unavailable');
        $this->getJson($this->endpoint())->assertStatus(422)
            ->assertJsonPath('message', 'Este modo requiere el Buzón y sus listados ya instalados. No se ejecutaron comandos.');
    }

    public function test_missing_column_after_a_reported_success_is_detected(): void
    {
        Schema::table('buzon_entries', function ($table): void {
            $table->dropIndex(['age_group']);
            $table->dropColumn('age_group');
        });
        foreach (self::commands() as [$name, $parameters]) Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        Artisan::shouldReceive('output')->times(6)->andReturn('Nothing to migrate');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('No se confirmó la columna age_group.')
            ->assertDontSee('PERMISSION CACHE:');
    }

    public function test_previous_birth_date_schema_is_required_without_running_its_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        Schema::rename('beneficiaries', 'beneficiaries_unavailable');
        $this->getJson($this->endpoint())->assertStatus(422)
            ->assertJsonPath('message', 'Este modo requiere la fecha de nacimiento de beneficiarios ya instalada. No se ejecutaron comandos.');
    }

    public static function failures(): array
    {
        return array_map(fn ($index) => [$index], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_a_failed_command_stops_the_remaining_steps(int $failure): void
    {
        foreach (self::commands() as $index => [$name, $parameters]) {
            if ($index > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($index === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }
}
