<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenancePermissionsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_23_180000_add_beneficiary_management_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        // Never read the real deployment token or run maintenance commands in tests.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            protected function maintenanceToken(): string
            {
                return 'test-maintenance-token';
            }
        });
    }

    private function endpoint(array $parameters = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'token' => 'test-maintenance-token',
            'only' => 'permisos-beneficiarios',
        ], $parameters));
    }

    public function test_permission_mode_only_runs_its_migration_and_cache_commands(): void
    {
        foreach ([
            ['optimize:clear', []],
            ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ] as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(6)->andReturn('OK');

        $this->get($this->endpoint())->assertOk()
            ->assertSee('PERMISOS DE BENEFICIARIOS')
            ->assertSee('No se importarán ni modificarán registros o beneficiarios.')
            ->assertSee('PERMISSION CACHE:');
    }

    public function test_invalid_token_cannot_execute_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
    }

    public function test_conflicting_and_unknown_modes_fail_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '1']))->assertStatus(422);
        }
        $this->get($this->endpoint(['only' => 'permisos-beneficiario']))->assertStatus(422);
    }

    public function test_migration_failure_stops_cache_warmup_and_reports_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])
            ->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Command output');

        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido');
    }

    public function test_existing_cache_only_mode_does_not_run_migrations(): void
    {
        foreach (['optimize:clear', 'cache:clear', 'config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command, [])->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(5)->andReturn('OK');
        $this->get($this->endpoint(['only' => 'cache']))->assertOk();
    }
}
