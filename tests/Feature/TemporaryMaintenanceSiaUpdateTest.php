<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceSiaUpdateTest extends TestCase
{
    private const MIGRATIONS = [
        'database/migrations/2026_10_08_120000_create_user_presences_table.php',
        'database/migrations/2026_10_09_120000_create_system_notifications_table.php',
        'database/migrations/2026_10_09_120100_add_receive_notifications_permission.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Never read the real deployment token or execute real Artisan commands.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;

            public string $token = 'test-sia-update-token';

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
            'only' => 'actualizacion-sia', 'token' => 'test-sia-update-token',
        ], $parameters));
    }

    public function test_combined_mode_runs_exactly_three_targeted_migrations_and_caches_even_on_repeat(): void
    {
        $commands = array_merge([['optimize:clear', []], ['cache:clear', []]],
            array_map(fn ($path) => ['migrate', ['--path' => $path, '--force' => true]], self::MIGRATIONS),
            [['config:cache', []], ['route:cache', []], ['view:cache', []]]);
        for ($run = 0; $run < 2; $run++) {
            foreach ($commands as [$command, $parameters]) {
                Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
            }
        }
        Artisan::shouldReceive('output')->times(16)->andReturn('Nothing to migrate.');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()
                ->assertSee('ACTUALIZACIÓN SIA:')->assertSee('No se ejecutan seeders ni importaciones.')
                ->assertSee('No se activa ni se cancela el mantenimiento')
                ->assertSee('conserva los permisos retirados')->assertSee('PERMISSION CACHE:');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $response->assertDontSee('test-sia-update-token');
        }
    }

    public function test_invalid_credentials_modes_and_conflicting_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get($this->endpoint(['token' => '']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-sia')->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '0']))->assertStatus(422);
        }
        $this->get($this->endpoint(['only' => 'actualizacion-sia-incorrecta']))->assertStatus(422);
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_incomplete_upload_is_rejected_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (array_merge(self::MIGRATIONS, [
            'app/Models/UserPresence.php', 'app/Models/SystemNotification.php', 'app/Models/SystemSetting.php',
            'app/Models/User.php', 'app/Support/SystemNotifications.php',
            'app/Http/Controllers/AuthController.php', 'app/Http/Controllers/ReportController.php',
            'app/Http/Controllers/PermissionController.php', 'app/Http/Controllers/SystemMaintenanceController.php',
            'app/Http/Controllers/TemporaryMaintenanceController.php',
            'resources/views/layouts/app.blade.php', 'resources/views/layouts/partials/configuration-menu.blade.php',
            'resources/views/layouts/partials/desktop-notifications.blade.php',
            'resources/views/maintenance/index.blade.php', 'resources/views/maintenance/live-notice.blade.php',
            'resources/views/reports/create.blade.php', 'resources/views/reports/show.blade.php',
            'resources/views/beneficiaries/summary.blade.php',
            'public/css/system-maintenance.css', 'public/css/maintenance-banner.css',
            'public/js/desktop-notifications.js', 'public/js/system-live-maintenance.js',
            'public/js/report-export.js', 'public/js/pwa.js', 'public/service-worker.js',
            'routes/web.php', 'routes/maintenance.php',
        ]) as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_nonzero_migration_exit_stops_before_notifications_and_warmup(): void
    {
        foreach (['optimize:clear', 'cache:clear'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command, [])->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()
            ->with('migrate', ['--path' => self::MIGRATIONS[0], '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failed');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_thrown_migration_error_stops_and_is_escaped(): void
    {
        foreach (['optimize:clear', 'cache:clear'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command, [])->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()
            ->with('migrate', ['--path' => self::MIGRATIONS[0], '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<script>failed</script>'));
        Artisan::shouldReceive('output')->times(2)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;script&gt;failed&lt;/script&gt;', false)
            ->assertDontSee('<script>failed</script>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
