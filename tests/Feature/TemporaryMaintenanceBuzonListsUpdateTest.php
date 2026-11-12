<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceBuzonListsUpdateTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_11_120000_create_buzon_lists.php';
    private const FILES = [
        self::MIGRATION,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/BuzonController.php', 'app/Http/Controllers/ProyectoController.php',
        'app/Http/Requests/SaveBuzonEntryRequest.php',
        'app/Models/BuzonList.php', 'app/Models/BuzonEntry.php', 'app/Models/Proyecto.php',
        'app/Models/User.php', 'app/Models/UserGroup.php',
        'app/Models/SystemSetting.php', 'app/Models/ReportingPeriod.php',
        'app/Providers/AppServiceProvider.php', 'app/Support/PeriodClosingNotice.php', 'app/Support/ReportPeriod.php',
        'app/Http/Middleware/PrivateBuzonResponse.php', 'app/Http/Middleware/RecordAuditLog.php',
        'resources/views/buzon/form.blade.php', 'resources/views/buzon/index.blade.php',
        'resources/views/buzon/lists/index.blade.php', 'resources/views/buzon/lists/show.blade.php',
        'resources/views/buzon/lists/partials/entries.blade.php', 'resources/views/buzon/partials/delete-button.blade.php',
        'resources/views/layouts/app.blade.php', 'resources/views/layouts/partials/period-closing-notice.blade.php',
        'public/css/buzon.css', 'public/css/period-closing-notice.css',
        'public/js/buzon.js', 'public/js/buzon-dictation.js', 'public/js/buzon-voice-data.js',
        'public/js/period-closing-notice.js', 'routes/web.php', 'routes/maintenance.php',
    ];
    private const MARKERS = [
        'app/Providers/AppServiceProvider.php', 'resources/views/layouts/app.blade.php',
        'resources/views/buzon/form.blade.php', 'resources/views/buzon/index.blade.php',
        'app/Http/Requests/SaveBuzonEntryRequest.php', 'app/Http/Controllers/ProyectoController.php', 'routes/web.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Only a fake token and mocked Artisan commands: never run real maintenance.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public string $token = 'test-lists-maintenance-token';
            public ?string $missing = null;
            public ?string $stale = null;
            public bool $oldGroupScopes = false;
            public bool $oldRuntime = false;

            protected function maintenanceToken(): string {return $this->token;}
            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
            protected function groupAuthorScopesAvailable(): bool
            {
                return ! $this->oldGroupScopes && parent::groupAuthorScopesAvailable();
            }
            protected function buzonListsRuntimeAvailable(): bool
            {
                return ! $this->oldRuntime && parent::buzonListsRuntimeAvailable();
            }
            protected function deploymentFileContains(string $path, string $marker): bool
            {
                return $path !== $this->stale && parent::deploymentFileContains($path, $marker);
            }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'actualizacion-buzon-listados', 'token' => 'test-lists-maintenance-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_lists_migration_and_cache_commands_run_in_order_on_repeated_calls(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()->assertSee('ACTUALIZACIÓN DE LISTADOS DEL BUZÓN Y AVISO DE CIERRE:')
                ->assertSee('únicamente la nueva migración de listados')
                ->assertSee('No agrupa, modifica ni elimina opiniones históricas.')
                ->assertSee('Se conservan los cuatro permisos existentes del Buzón')
                ->assertSee('edición cruzada habilitada')->assertSee('Crear sin ver')
                ->assertSee('cinco días antes del último día del mes')->assertSee('America/Caracas')
                ->assertSee('El aviso no cierra períodos')->assertSee('ni envía datos al donante')
                ->assertSee('tampoco activa ni cancela bloqueos o avisos programados.')
                ->assertSee('No se ejecutan seeders, importaciones, correcciones de parroquias ni migraciones de permisos.')
                ->assertSee('No se crean ni se eliminan registros, beneficiarios, opiniones o listados')
                ->assertSee('La migración ya aplicada se omite.')->assertSee('No ejecuta todas las migraciones pendientes.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-lists-maintenance-token')
                ->assertHeader('Cache-Control');
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function test_missing_token_wrong_mode_and_conflicting_options_never_execute_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-buzon-listados')->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-buzon-listados-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_all_runtime_files_are_checked_before_any_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_missing_methods_in_old_models_or_controllers_stop_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        $controller->oldGroupScopes = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba app/Models/User.php actualizado: el Buzón necesita los métodos públicos de autores visibles y editables del grupo.');
        $controller->oldGroupScopes = false;
        $controller->oldRuntime = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba los modelos, el controlador del Buzón y PeriodClosingNotice actualizados: faltan métodos de listados o del aviso de cierre.');
    }

    public function test_outdated_provider_views_routes_and_request_are_detected_even_when_files_exist(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::MARKERS as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
                'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public static function failures(): array
    {
        return array_map(fn ($stage) => [$stage], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_failed_command_stops_all_later_commands(int $failure): void
    {
        foreach (self::commands() as $stage => [$name, $parameters]) {
            if ($stage > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($stage === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_migration_exception_is_escaped_and_caches_are_not_rebuilt_after_failure(): void
    {
        foreach (array_slice(self::commands(), 0, 2) as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<controlled failure>'));
        Artisan::shouldReceive('output')->twice()->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;controlled failure&gt;', false)
            ->assertDontSee('<controlled failure>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
