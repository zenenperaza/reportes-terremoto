<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AutomaticBackupService;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_service_creates_the_missing_private_directory(): void
    {
        Storage::fake('local');
        Storage::disk('local')->deleteDirectory(DatabaseBackupService::DIRECTORY);

        $method = new \ReflectionMethod(DatabaseBackupService::class, 'ensureBackupDirectory');
        $directory = $method->invoke(new DatabaseBackupService, Storage::disk('local'));

        $this->assertDirectoryExists($directory);
        Storage::disk('local')->assertMissing(DatabaseBackupService::DIRECTORY.'/.write-test');
    }

    public function test_old_backups_are_removed_after_fifteen_days(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-09-11 10:00:00');
        $oldBackup = 'backups/asonacop-2026-08-20_10-00-00-abcdef.sql.gz';
        $recentBackup = 'backups/asonacop-2026-09-10_10-00-00-fedcba.sql.gz';
        Storage::disk('local')->put($oldBackup, 'old');
        Storage::disk('local')->put($recentBackup, 'recent');
        touch(Storage::disk('local')->path($oldBackup), now()->subDays(20)->timestamp);
        touch(Storage::disk('local')->path($recentBackup), now()->subDay()->timestamp);

        $deleted = (new DatabaseBackupService)->pruneOlderThanDays(15);

        $this->assertSame(1, $deleted);
        Storage::disk('local')->assertMissing($oldBackup);
        Storage::disk('local')->assertExists($recentBackup);
    }

    public function test_automatic_backup_runs_only_once_per_day(): void
    {
        Carbon::setTestNow('2026-09-11 08:30:00');
        $backupService = Mockery::mock(DatabaseBackupService::class);
        $backupService->shouldReceive('create')->once()->andReturn('asonacop-automatic.sql.gz');
        $backupService->shouldReceive('pruneOlderThanDays')->once()->with(15)->andReturn(2);
        $automaticBackupService = new AutomaticBackupService($backupService);

        $this->assertSame('asonacop-automatic.sql.gz', $automaticBackupService->runIfDue());
        $this->assertNull($automaticBackupService->runIfDue());
        $this->assertDatabaseHas('system_settings', [
            'key' => SystemSetting::AUTOMATIC_BACKUP_LAST_AT,
        ]);
    }

    public function test_only_administrators_can_manage_private_backups(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'reporter']);
        $filename = 'asonacop-2026-09-03_15-00-00-abcdef.sql.gz';
        Storage::disk('local')->put('backups/'.$filename, 'compressed database');

        $this->actingAs($reporter)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Respaldos disponibles')
            ->assertSee('Respaldo autom&aacute;tico diario activo', false)
            ->assertSee('15 d&iacute;as', false)
            ->assertSee($filename);

        $this->actingAs($admin)->get(route('backups.download', $filename))
            ->assertOk()
            ->assertDownload($filename);

        $this->actingAs($admin)->delete(route('backups.destroy', $filename))
            ->assertRedirect(route('backups.index'));
        Storage::disk('local')->assertMissing('backups/'.$filename);
    }

    public function test_invalid_backup_paths_are_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/configuracion/respaldos/archivo.txt/descargar')->assertNotFound();
    }

    public function test_each_backup_action_requires_its_own_permission(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'reporter']);
        $filename = 'asonacop-2026-09-04_09-00-00-abcdef.sql.gz';
        Storage::disk('local')->put('backups/'.$filename, 'compressed database');
        $user->givePermissionTo('descargar respaldos');

        $this->actingAs($user)->get(route('backups.index'))
            ->assertOk()
            ->assertDontSee('Generar respaldo')
            ->assertDontSee('aria-label="Eliminar respaldo"', false)
            ->assertSee('aria-label="Descargar respaldo"', false);
        $this->actingAs($user)->get(route('backups.download', $filename))->assertOk();
        $this->actingAs($user)->post(route('backups.store'))->assertForbidden();
        $this->actingAs($user)->delete(route('backups.destroy', $filename))->assertForbidden();
    }
}
