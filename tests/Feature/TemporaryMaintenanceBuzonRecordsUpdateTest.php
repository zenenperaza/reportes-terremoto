<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceBuzonRecordsUpdateTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_04_120000_add_buzon_action_permissions.php';

    private const FILES = [
        self::MIGRATION,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/BuzonController.php',
        'app/Http/Controllers/PermissionController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Requests/ContinueReportBeneficiaryRequest.php',
        'app/Http/Requests/SaveBuzonEntryRequest.php',
        'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
        'app/Http/Requests/StoreReportRequest.php',
        'app/Http/Requests/UpdateBeneficiaryRequest.php',
        'app/Http/Requests/Concerns/ValidatesBeneficiaryCaregiver.php',
        'app/Http/Requests/Concerns/PreservesReportLocation.php',
        'app/Http/Requests/Concerns/ValidatesAssociatedIndicators.php',
        'app/Http/Middleware/PrivateBuzonResponse.php',
        'app/Http/Middleware/RecordAuditLog.php',
        'app/Http/Middleware/ReportPeriodTransaction.php',
        'app/Models/BuzonEntry.php',
        'app/Models/User.php', 'app/Models/UserGroup.php',
        'app/Services/ReportContinuation.php',
        'app/Services/ReportDataTable.php',
        'app/Support/ReportPeriod.php', 'config/reports.php',
        'resources/views/buzon/form.blade.php',
        'resources/views/buzon/index.blade.php',
        'resources/views/buzon/partials/delete-button.blade.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/proyectos/show.blade.php',
        'resources/views/reports/create.blade.php',
        'resources/views/reports/index.blade.php',
        'resources/views/reports/show.blade.php',
        'public/css/buzon.css', 'public/css/report-form-fixes.css',
        'public/js/buzon.js',
        'routes/web.php', 'routes/maintenance.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Never read a real token or run real maintenance commands in these tests.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;
            public bool $oldUserModel = false;
            public string $token = 'test-buzon-records-token';

            protected function maintenanceToken(): string {return $this->token;}

            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }

            protected function groupAuthorScopesAvailable(): bool
            {
                return ! $this->oldUserModel && parent::groupAuthorScopesAvailable();
            }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'actualizacion-buzon-registros', 'token' => 'test-buzon-records-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_new_buzon_permission_migration_and_caches_run_in_order_on_repeat(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('ACTUALIZACIÓN DE BUZÓN Y CONTINUACIÓN DE REGISTROS:')
                ->assertSee('ver buzon, crear buzon, editar buzon y eliminar buzon')
                ->assertSee('no reciben eliminar automáticamente.')
                ->assertSee('opiniones propias y de sus grupos activos')
                ->assertSee('edición cruzada habilitada')
                ->assertSee('Continuar agregando beneficiarios')
                ->assertSee('Los datos del cuidador son opcionales')
                ->assertSee('No se ejecutan seeders, importaciones ni correcciones de parroquias.')
                ->assertSee('No se crean, eliminan, unen ni reclasifican registros, beneficiarios u opiniones')
                ->assertSee('se conservan los bloqueos y avisos programados.')
                ->assertSee('La migración ya aplicada se omite.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-buzon-records-token')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_invalid_token_mode_or_conflicting_options_never_execute_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-buzon-registros')->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-buzon-registros-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $name) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$name => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_every_required_file_is_checked_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_old_user_model_stops_before_changing_caches_or_permissions(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->app->make(TemporaryMaintenanceController::class)->oldUserModel = true;
        $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message',
            'Suba app/Models/User.php actualizado: el Buzón necesita los métodos públicos de autores visibles y editables del grupo.');
    }

    public static function failureStages(): array
    {
        return array_map(fn ($stage) => [$stage], array_keys(self::commands()));
    }

    #[DataProvider('failureStages')]
    public function test_command_failure_stops_all_following_steps(int $failureStage): void
    {
        foreach (self::commands() as $stage => [$command, $parameters]) {
            if ($stage > $failureStage) break;
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn($stage === $failureStage ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failureStage + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_migration_exception_is_escaped_and_no_warmup_runs_after_failure(): void
    {
        foreach (array_slice(self::commands(), 0, 2) as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<fallo controlado>'));
        Artisan::shouldReceive('output')->twice()->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo controlado&gt;', false)
            ->assertDontSee('<fallo controlado>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
