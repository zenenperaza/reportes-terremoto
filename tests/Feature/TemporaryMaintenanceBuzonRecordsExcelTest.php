<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class TemporaryMaintenanceBuzonRecordsExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Synthetic credentials, SQLite in memory and mocked commands: no live maintenance.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController {
            public string $token = 'test-release-token';
            public ?string $missing = null;
            public ?string $stale = null;
            public bool $oldRuntime = false;
            protected function maintenanceToken(): string { return $this->token; }
            protected function deploymentFileExists(string $file): bool { return $file !== $this->missing && parent::deploymentFileExists($file); }
            protected function deploymentFileContains(string $file, string $marker): bool { return $file !== $this->stale && parent::deploymentFileContains($file, $marker); }
            protected function buzonRecordsExcelRuntimeAvailable(): bool { return ! $this->oldRuntime && parent::buzonRecordsExcelRuntimeAvailable(); }
            public function requiredFiles(): array { return parent::buzonRecordsExcelFiles(); }
            public function markerFiles(): array { return array_keys(parent::buzonRecordsExcelMarkers()); }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'actualizacion-buzon-registros-excel', 'token' => 'test-release-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => 'database/migrations/2026_11_23_120000_add_buzon_export_permission.php', '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    private function expectCommands(): void
    {
        foreach (self::commands() as [$name, $parameters]) Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        Artisan::shouldReceive('output')->times(count(self::commands()))->andReturn('OK');
    }

    public function test_release_only_runs_the_export_permission_migration_and_caches_without_restoring_revoked_grants(): void
    {
        $this->expectCommands();
        $this->get($this->endpoint())->assertOk()->assertSee('ACTUALIZACIÓN DE BUZÓN, REGISTROS Y EXCEL:')
            ->assertSee('únicamente la migración del permiso exportar buzon')
            ->assertSee('Buzón público en su submenú')->assertSee('consulta exclusiva de opiniones externas')
            ->assertSee('motivo')->assertSee('misma transacción')->assertSee('Nombre y apellido completo')
            ->assertSee('solo para administradores')->assertSee('No se activa ni se cancela el mantenimiento')
            ->assertSee('No se ejecutan seeders')->assertSee('no instala el código')->assertSee('PERMISSION CACHE:')
            ->assertDontSee('test-release-token')->assertDontSee('DB:SEED')->assertDontSee('BACKUP:AUTOMATIC')
            ->assertHeader('Cache-Control', 'no-store, private');

        $role = Role::findByName('admin', 'web');
        $role->revokePermissionTo('exportar buzon');
        $grantsBefore = $role->permissions()->orderBy('id')->pluck('id')->all();
        $this->expectCommands();
        $this->get($this->endpoint())->assertOk()->assertSee('La migración ya aplicada se omite');
        $this->assertSame($grantsBefore, $role->permissions()->orderBy('id')->pluck('id')->all());
        $this->assertFalse($role->fresh()->hasPermissionTo('exportar buzon'));
    }

    public function test_unknown_modes_invalid_credentials_and_mixed_options_execute_no_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get($this->endpoint(['token' => null]))->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-buzon-registros-excel-typo']))->assertStatus(422);
        $this->get($this->endpoint(['only' => null, 'nly' => 'actualizacion-buzon-registros-excel']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_every_missing_runtime_file_prevents_all_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        $files = $controller->requiredFiles();
        foreach (['app/Services/ReportDeletionAudit.php', 'app/Services/BuzonDataTable.php',
            'resources/views/buzon/public/index.blade.php', 'resources/views/reports/lists.blade.php',
            'public/js/report-deletion.js', 'public/js/buzon-table.js',
            'database/migrations/2026_11_23_120000_add_buzon_export_permission.php'] as $essential) {
            $this->assertContains($essential, $files);
        }
        foreach ($files as $file) {
            $controller->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_stale_or_partial_deployment_prevents_all_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        $controller->oldRuntime = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba los controladores, servicios y modelos actualizados: faltan métodos del Buzón, listados o auditoría de eliminaciones.');
        $controller->oldRuntime = false;
        foreach ($controller->markerFiles() as $file) {
            $controller->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
                'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public function test_birth_date_and_previous_buzon_schema_are_required_without_running_old_migrations(): void
    {
        Artisan::shouldReceive('call')->never();
        Schema::table('beneficiaries', fn ($table) => $table->dropColumn('birth_date'));
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Este modo requiere las actualizaciones previas del Buzón y la columna birth_date en beneficiaries. Complete esas actualizaciones antes de continuar.');
    }

    public function test_missing_existing_permissions_are_not_silently_recreated(): void
    {
        Artisan::shouldReceive('call')->never();
        Permission::where('name', 'eliminar buzon')->delete();
        $this->getJson($this->endpoint())->assertStatus(422);
        $this->assertFalse(Permission::where('name', 'eliminar buzon')->exists());
    }

    public function test_missing_export_permission_after_commands_is_reported_without_implicit_repair(): void
    {
        Permission::where('name', 'exportar buzon')->delete();
        $this->expectCommands();
        $this->get($this->endpoint())->assertStatus(500)->assertSee('No se encontró el permiso exportar buzon')
            ->assertDontSee('PERMISSION CACHE:');
        $this->assertFalse(Permission::where('name', 'exportar buzon')->exists());
    }

    public static function failures(): array
    {
        return array_map(fn ($index) => [$index], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_command_failure_stops_the_remaining_stages(int $failure): void
    {
        foreach (self::commands() as $stage => [$name, $parameters]) {
            if ($stage > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($stage === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Controlled failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }
}
