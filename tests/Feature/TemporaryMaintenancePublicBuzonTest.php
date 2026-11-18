<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TemporaryMaintenancePublicBuzonTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = [
        'database/migrations/2026_11_17_120000_add_public_source_to_buzon_entries.php',
        'database/migrations/2026_11_17_140000_add_logo_to_donantes.php',
        'database/migrations/2026_11_17_150000_add_public_submission_key_to_buzon_entries.php',
    ];
    private const FILES = [
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/BuzonController.php', 'app/Http/Controllers/PublicBuzonController.php',
        'app/Http/Controllers/DonanteController.php', 'app/Http/Controllers/DonorLogoController.php',
        'app/Http/Requests/SavePublicBuzonEntryRequest.php', 'app/Http/Requests/SaveBuzonEntryRequest.php',
        'app/Http/Middleware/PublicBuzonAccess.php', 'app/Http/Middleware/RecordAuditLog.php',
        'app/Models/BuzonEntry.php', 'app/Models/Donante.php', 'app/Models/Proyecto.php',
        'app/Models/User.php', 'app/Models/UserGroup.php',
        'app/Support/PublicBuzonLink.php', 'app/Support/DonorLogo.php',
        'app/Providers/AppServiceProvider.php', 'bootstrap/app.php',
        'resources/views/buzon/index.blade.php', 'resources/views/buzon/form.blade.php',
        'resources/views/buzon/public/form.blade.php', 'resources/views/buzon/public/unavailable.blade.php',
        'resources/views/buzon/public/thanks.blade.php',
        'resources/views/donantes/create.blade.php', 'resources/views/donantes/edit.blade.php',
        'resources/views/donantes/form.blade.php',
        'public/css/buzon.css', 'public/css/buzon-public.css', 'public/css/donor-logo.css',
        'public/js/buzon-public-link.js', 'public/vendor/qrcodegen/qrcodegen.js',
        'public/js/buzon-public-submit.js',
        'public/js/buzon-share-project.js',
        'public/vendor/select2/js/jquery.min.js', 'public/vendor/select2/js/select2.full.min.js',
        'public/vendor/select2/css/select2.min.css',
        'public/vendor/qrcodegen/README.md', 'public/icons/asonacop-app.png',
        'routes/web.php', 'routes/maintenance.php',
    ];
    private const MARKER_FILES = [
        'app/Http/Controllers/BuzonController.php', 'app/Http/Controllers/PublicBuzonController.php',
        'app/Http/Controllers/DonanteController.php', 'app/Models/Donante.php', 'app/Models/BuzonEntry.php',
        'app/Http/Middleware/PublicBuzonAccess.php', 'app/Providers/AppServiceProvider.php', 'bootstrap/app.php',
        'resources/views/buzon/index.blade.php', 'resources/views/donantes/create.blade.php',
        'resources/views/donantes/edit.blade.php', 'resources/views/donantes/form.blade.php', 'routes/web.php',
        'app/Http/Requests/SavePublicBuzonEntryRequest.php', 'resources/views/buzon/public/form.blade.php',
        'resources/views/buzon/public/thanks.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Synthetic token, in-memory database and mocked Artisan: no real maintenance or credentials.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public string $token = 'test-public-maintenance-token';
            public ?string $missing = null;
            public ?string $stale = null;
            public bool $oldRuntime = false;
            public bool $oldGroupScopes = false;
            protected function maintenanceToken(): string {return $this->token;}
            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
            protected function deploymentFileContains(string $path, string $marker): bool
            {
                return $path !== $this->stale && parent::deploymentFileContains($path, $marker);
            }
            protected function buzonPublicRuntimeAvailable(): bool
            {
                return ! $this->oldRuntime && parent::buzonPublicRuntimeAvailable();
            }
            protected function groupAuthorScopesAvailable(): bool
            {
                return ! $this->oldGroupScopes && parent::groupAuthorScopesAvailable();
            }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'actualizacion-buzon-publico', 'token' => 'test-public-maintenance-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATIONS[0], '--force' => true]],
            ['migrate', ['--path' => self::MIGRATIONS[1], '--force' => true]],
            ['migrate', ['--path' => self::MIGRATIONS[2], '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_three_targeted_migrations_and_caches_run_in_order_even_when_repeated(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(16)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()
                ->assertSee('ACTUALIZACIÓN DE BUZÓN PÚBLICO, QR Y LOGOS DE DONANTES:')
                ->assertSee('únicamente tres migraciones')->assertSee('logo_path a Donantes')
                ->assertSee('public_submission_key')->assertSee('No se borran ni se unen duplicados ya existentes')
                ->assertSee('anteriores conservan sus autores')->assertSee('https://asonacop.org/')
                ->assertSee('logo se configura en Donantes, no en Proyectos')
                ->assertSee('No se ejecutan seeders, importaciones, correcciones de parroquias ni migraciones de permisos.')
                ->assertSee('No se crean, eliminan, agrupan ni reclasifican registros, beneficiarios, opiniones o listados')
                ->assertSee('No se activa ni se cancela el mantenimiento')->assertSee('edición cruzada')
                ->assertSee('Las migraciones ya aplicadas se omiten.')->assertSee('No ejecuta todas las migraciones pendientes.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-public-maintenance-token');
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function test_invalid_token_modes_typo_and_conflicting_options_execute_nothing(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-buzon-publico')->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-buzon-publico-invalid']))->assertStatus(422);
        // Previously mistyping only as nly could reach the legacy general mode. Now it fails closed.
        $this->get($this->endpoint(['only' => null, 'nly' => 'actualizacion-buzon-publico']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $key) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$key => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_every_missing_file_stops_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (array_merge(self::MIGRATIONS, self::FILES) as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_stale_runtime_or_partial_upload_stops_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $controller = $this->app->make(TemporaryMaintenanceController::class);
        foreach (['oldRuntime', 'oldGroupScopes'] as $flag) {
            $controller->$flag = true;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
                'Suba los modelos, controladores y soportes actualizados del Buzón público y del logo de Donantes: faltan métodos requeridos.');
            $controller->$flag = false;
        }
        foreach (self::MARKER_FILES as $file) {
            $controller->stale = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
                'El archivo '.$file.' no contiene la actualización esperada. Suba su versión actual antes de continuar.');
        }
    }

    public function test_missing_previous_schema_or_permission_stops_without_installing_old_features(): void
    {
        Artisan::shouldReceive('call')->never();
        Permission::where('name', 'eliminar buzon')->delete();
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Este modo requiere el Buzón ya instalado con sus listados y cuatro permisos. Complete esas actualizaciones antes de continuar.');
        Permission::create(['name' => 'eliminar buzon', 'guard_name' => 'web']);
        Schema::rename('buzon_lists', 'buzon_lists_not_installed');
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Este modo requiere el Buzón ya instalado con sus listados y cuatro permisos. Complete esas actualizaciones antes de continuar.');
    }

    public static function failures(): array
    {
        return array_map(fn ($stage) => [$stage], array_keys(self::commands()));
    }

    #[DataProvider('failures')]
    public function test_a_failed_command_stops_all_following_commands(int $failure): void
    {
        foreach (self::commands() as $stage => [$name, $parameters]) {
            if ($stage > $failure) break;
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn($stage === $failure ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failure + 1)->andReturn('Controlled failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_exception_is_escaped_and_later_commands_are_not_executed(): void
    {
        foreach (array_slice(self::commands(), 0, 2) as [$name, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($name, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATIONS[0], '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<controlled failure>'));
        Artisan::shouldReceive('output')->twice()->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;controlled failure&gt;', false)
            ->assertDontSee('<controlled failure>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
