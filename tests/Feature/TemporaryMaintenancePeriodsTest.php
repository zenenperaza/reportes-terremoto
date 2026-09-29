<?php

namespace Tests\Feature;

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryMaintenancePeriodsTest extends TestCase
{
    private const FIRST = 'database/migrations/2026_09_28_120000_add_reporting_period_to_reports.php';
    private const SECOND = 'database/migrations/2026_09_28_130000_create_reporting_periods_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindController();
    }

    private function bindController(?string $missing = null): void
    {
        // No real token reads, maintenance commands, or deployment writes in tests.
        $this->app->instance(TemporaryMaintenanceController::class, new class($missing) extends TemporaryMaintenanceController
        {
            public function __construct(public ?string $missing) {}
            protected function maintenanceToken(): string { return 'test-period-token'; }
            protected function deploymentFileExists(string $path): bool
            {
                return $path !== $this->missing && parent::deploymentFileExists($path);
            }
        });
    }

    private function endpoint(array $parameters = []): string
    {
        return '/ejecutar-migraciones-temp?'.http_build_query(array_merge([
            'token' => 'test-period-token', 'only' => 'periodos',
        ], $parameters));
    }

    public function test_period_mode_only_runs_ordered_period_migrations_and_caches(): void
    {
        foreach ([
            ['optimize:clear', []], ['cache:clear', []],
            ['migrate', ['--path' => self::FIRST, '--force' => true]],
            ['migrate', ['--path' => self::SECOND, '--force' => true]],
            ['config:cache', []], ['route:cache', []], ['view:cache', []],
        ] as [$command, $parameters]) {
            Artisan::shouldReceive('call')->once()->with($command, $parameters)->ordered()->andReturn(0);
        }
        Artisan::shouldReceive('output')->times(7)->andReturn('OK');
        $this->get($this->endpoint())->assertOk()->assertSee('PERÍODOS')
            ->assertSee('No se ejecutan seeders ni importaciones.')
            ->assertSee('PERMISSION CACHE:');
    }

    public function test_period_mode_rejects_invalid_token_and_mixed_flags_without_commands(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->get($this->endpoint(['token' => 'invalid']))->assertForbidden();
        foreach (['only_cache', 'import_excel', 'apply', 'decisions'] as $option) {
            $this->get($this->endpoint([$option => '1']))->assertStatus(422);
        }
    }

    public function test_missing_upload_is_reported_before_clearing_caches_or_migrating(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach ([self::FIRST, self::SECOND, 'app/Models/ReportingPeriod.php',
            'app/Support/ReportPeriod.php', 'app/Http/Middleware/ReportPeriodTransaction.php',
            'resources/views/reports/partials/period-filter.blade.php', 'public/css/system-configuration.css',
            'public/js/navigation-disclosure.js'] as $file) {
            $this->app->make(TemporaryMaintenanceController::class)->missing = $file;
            $this->getJson($this->endpoint())->assertStatus(422)->assertJsonPath('message', 'Suba el archivo '.$file.' antes de continuar.');
        }
    }

    public function test_failed_first_migration_never_starts_second_or_warms_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::FIRST, '--force' => true])->ordered()->andReturn(1);
        Artisan::shouldReceive('output')->times(3)->andReturn('Failure');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('Proceso detenido');
    }

    public function test_second_migration_exception_is_reported_without_warming_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear', [])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::FIRST, '--force' => true])->ordered()->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => self::SECOND, '--force' => true])->ordered()->andThrow(new \RuntimeException('Fallo de prueba'));
        Artisan::shouldReceive('output')->times(3)->andReturn('OK');
        $this->get($this->endpoint())->assertStatus(500)->assertSee('ERROR: Fallo de prueba');
    }
}
