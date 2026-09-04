<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

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
