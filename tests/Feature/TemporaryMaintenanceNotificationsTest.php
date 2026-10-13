<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenanceNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController {
            public ?string $missing = null;
            protected function maintenanceToken(): string { return 'test-notifications-token'; }
            protected function deploymentFileExists(string $path): bool { return $path !== $this->missing && parent::deploymentFileExists($path); }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'only' => 'notificaciones', 'token' => 'test-notifications-token',
        ], $options));
    }

    public function test_notification_mode_runs_only_two_targeted_migrations_and_caches(): void
    {
        foreach ([['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => 'database/migrations/2026_10_09_120000_create_system_notifications_table.php', '--force' => true]],
            ['migrate', ['--path' => 'database/migrations/2026_10_09_120100_add_receive_notifications_permission.php', '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]] as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(7)->andReturn('OK');
        $this->get($this->endpoint())->assertOk()->assertSee('NOTIFICACIONES:')
            ->assertSee('No se ejecutan seeders ni importaciones');
    }

    public function test_wrong_token_conflicting_options_and_incomplete_upload_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '0']))->assertStatus(422);
        }
        foreach (['app/Support/SystemNotifications.php', 'public/js/desktop-notifications.js',
            'public/js/pwa.js', 'public/service-worker.js',
            'database/migrations/2026_10_09_120000_create_system_notifications_table.php',
            'database/migrations/2026_10_09_120100_add_receive_notifications_permission.php'] as $path) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $path;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$path.' antes de continuar.');
        }
    }
}
