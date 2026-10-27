<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TemporaryMaintenanceNationalityTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_27_120000_add_nationality_to_beneficiaries_table.php';

    private const REQUIRED_FILES = [
        self::MIGRATION,
        'app/Http/Controllers/TemporaryMaintenanceController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Requests/StoreReportRequest.php',
        'app/Http/Requests/StoreBeneficiaryEntryRequest.php',
        'app/Http/Requests/UpdateBeneficiaryRequest.php',
        'app/Http/Requests/UpdateBeneficiaryAttentionRequest.php',
        'app/Models/Beneficiary.php', 'config/reports.php',
        'resources/views/reports/create.blade.php',
        'resources/views/reports/show.blade.php',
        'resources/views/reports/index.blade.php',
        'resources/views/beneficiaries/summary.blade.php',
        'resources/views/reports/partials/summary-indicator-filter.blade.php',
        'public/css/beneficiary-summary.css', 'public/css/report-datatable.css',
        'public/js/summary-indicator-filter.js',
        'routes/web.php', 'routes/maintenance.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // All deployment commands are mocked; never read real credentials.
        $this->app->instance(TemporaryMaintenanceController::class, new class extends TemporaryMaintenanceController
        {
            public ?string $missing = null;
            public string $token = 'test-nationality-token';

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

    private function endpoint(array $options = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'only' => 'nacionalidad-beneficiarios', 'token' => 'test-nationality-token',
        ], $options));
    }

    private static function commands(): array
    {
        return [
            ['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::MIGRATION, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []],
        ];
    }

    public function test_only_nationality_migration_and_caches_run_in_order_and_can_be_repeated(): void
    {
        for ($run = 0; $run < 2; $run++) {
            foreach (self::commands() as [$command, $parameters]) {
                Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
            }
        }
        Artisan::shouldReceive('output')->times(12)->andReturn('OK');
        for ($run = 0; $run < 2; $run++) {
            $response = $this->get($this->endpoint())->assertOk()
                ->assertSee('NACIONALIDAD DE BENEFICIARIOS Y DISEÑO DE FILTROS:')
                ->assertSee('No se rellena la nacionalidad de beneficiarios anteriores')
                ->assertSee('Venezolana predeterminada para nuevos beneficiarios')
                ->assertSee('No se ejecutan seeders, importaciones ni correcciones de parroquias.')
                ->assertSee('La migración ya aplicada se omite.')
                ->assertSee('se conservan los bloqueos y avisos programados.')
                ->assertSee('Este modo no instala Indicadores Resumen')
                ->assertSee('PERMISSION CACHE:')->assertDontSee('test-nationality-token');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_invalid_tokens_mode_and_conflicting_options_execute_no_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'wrong']))->assertForbidden();
        $this->get('/ejecutar-migraciones-temp?only=nacionalidad-beneficiarios')->assertForbidden();
        $this->get($this->endpoint(['only' => 'nacionalidad-beneficiarios-invalid']))->assertStatus(422);
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $field) {
            foreach (['0', '1', ''] as $value) {
                $this->get($this->endpoint([$field => $value]))->assertStatus(422);
            }
        }
        $this->app->make(TemporaryMaintenanceController::class)->token = '';
        $this->get($this->endpoint())->assertStatus(503);
    }

    public function test_missing_upload_files_stop_before_cache_clear_or_migration(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (self::REQUIRED_FILES as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)
                ->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public static function failureStages(): array
    {
        return array_map(fn (int $stage) => [$stage], array_keys(self::commands()));
    }

    #[DataProvider('failureStages')]
    public function test_failure_at_any_stage_prevents_subsequent_commands(int $failureStage): void
    {
        foreach (self::commands() as $stage => [$command, $parameters]) {
            if ($stage > $failureStage) break;
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()
                ->andReturn($stage === $failureStage ? 1 : 0);
        }
        Artisan::shouldReceive('output')->times($failureStage + 1)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido')->assertDontSee('PERMISSION CACHE:');
    }

    public function test_migration_exception_is_escaped_and_stops_cache_warmup(): void
    {
        foreach (array_slice(self::commands(), 0, 2) as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::MIGRATION, '--force' => true])->ordered()
            ->andThrow(new RuntimeException('<fallo de prueba>'));
        Artisan::shouldReceive('output')->times(2)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('&lt;fallo de prueba&gt;', false)
            ->assertDontSee('<fallo de prueba>', false)->assertDontSee('PERMISSION CACHE:');
    }
}
