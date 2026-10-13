<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenanceLiveTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_08_120000_create_user_presences_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;

            protected function maintenanceToken(): string
            {
                return 'test-live-token';
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
            'only' => 'mantenimiento-en-vivo', 'token' => 'test-live-token',
        ], $parameters));
    }

    public function test_live_mode_runs_only_presence_migration_and_caches_with_no_seeds_or_imports(): void
    {
        foreach ([['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]] as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(6)->andReturn('OK');
        $this->get($this->endpoint())->assertOk()->assertSee('MANTENIMIENTO EN VIVO:')
            ->assertSee('No se ejecutan seeders ni importaciones.')->assertSee('Repetir este modo conserva los bloqueos');
    }

    public function test_bad_token_conflicting_modes_and_missing_files_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'bad']))->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $field) {
            $this->get($this->endpoint([$field => '0']))->assertStatus(422);
        }
        foreach ([self::MIGRATION, 'app/Models/UserPresence.php', 'app/Models/SystemSetting.php',
            'app/Models/User.php', 'app/Models/SystemNotification.php', 'app/Support/SystemNotifications.php',
            'app/Http/Controllers/SystemMaintenanceController.php', 'app/Http/Controllers/AuthController.php',
            'resources/views/maintenance/index.blade.php', 'resources/views/maintenance/live-notice.blade.php',
            'resources/views/layouts/app.blade.php', 'public/css/system-maintenance.css',
            'resources/views/layouts/partials/desktop-notifications.blade.php', 'public/js/desktop-notifications.js',
            'public/css/maintenance-banner.css', 'public/js/system-live-maintenance.js', 'routes/web.php'] as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_failure_stops_before_warmup(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failed');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }
}
