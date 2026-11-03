<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceBeneficiaryUpdateTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_02_120000_add_caregiver_fields_to_beneficiaries.php';

    private const FILES = [
        self::MIGRATION,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/BeneficiaryLookupController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Middleware/RecordAuditLog.php',
        'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
        'app/Http/Requests/StoreReportRequest.php',
        'app/Http/Requests/UpdateBeneficiaryRequest.php',
        'app/Http/Requests/UpdateBeneficiaryAttentionRequest.php',
        'app/Http/Requests/Concerns/ValidatesBeneficiaryCaregiver.php',
        'app/Services/BeneficiaryRecurrence.php',
        'app/Models/Beneficiary.php', 'config/reports.php',
        'resources/views/reports/create.blade.php',
        'resources/views/reports/index.blade.php',
        'resources/views/reports/show.blade.php',
        'public/css/beneficiary-entry.css', 'public/css/report-datatable.css',
        'public/js/beneficiary-id-autofill.js', 'public/js/beneficiary-voice.js',
        'public/js/beneficiary-recurrence.js', 'public/js/record-project-filter.js',
        'routes/web.php', 'routes/maintenance.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Do not read production tokens or run real maintenance commands.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;
            public string $token = 'test-beneficiary-token';

            protected function maintenanceToken(): string {return $this->token;}

            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
        });
    }

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_replace([
            'only' => 'actualizacion-beneficiarios', 'token' => 'test-beneficiary-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []]];
    }

    public function test_only_caregiver_migration_and_caches_run_in_order_and_mode_can_be_repeated(): void
    {
        for ($run = 0; $run < 2; $run++) foreach (self::commands() as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $this->get($this->endpoint())->assertOk()->assertSee('ACTUALIZACIÓN DE BENEFICIARIOS Y REGISTROS:')
                ->assertSee('No se rellenan datos de beneficiarios anteriores.')
                ->assertSee('No se reclasifican los registros históricos')
                ->assertSee('No se ejecutan seeders, importaciones ni correcciones de parroquias.')
                ->assertSee('se conservan los bloqueos y avisos programados.')
                ->assertSee('La migración ya aplicada se omite.')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-beneficiary-token')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_token_and_mode_validation_stop_before_any_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=actualizacion-beneficiarios')->assertForbidden();
        $this->get($this->endpoint(['only' => 'actualizacion-beneficiarios-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $name) foreach (['', '0', '1'] as $value) {
            $this->get($this->endpoint([$name => $value]))->assertStatus(422);
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_file_in_pending_upload_stops_before_migration_or_cache_changes(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
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

    public function test_migration_exception_is_escaped_and_does_not_rebuild_caches_after_failure(): void
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
