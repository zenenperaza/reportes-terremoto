<?php

namespace Tests\Feature;

use App\Models\{AuditLog, BuzonEntry, Donante, Proyecto, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuzonActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $project;
    private Proyecto $other;
    private BuzonEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $donor = Donante::create(['nombre' => 'Pruebas de permisos', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'BUZON-A', 'descripcion' => 'Proyecto A', 'estatus' => true]);
        $this->other = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'BUZON-B', 'descripcion' => 'Proyecto B', 'estatus' => true]);
        $this->entry = BuzonEntry::create($this->payload() + ['created_by' => null]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['proyecto_id' => $this->project->id, 'opinion_date' => today()->toDateString(),
            'place_name' => 'Lugar de prueba', 'opinion' => 'very_good', 'feedback_type' => 'comment',
            'comment' => 'Contenido reservado de prueba'], $changes);
    }

    private function user(array $permissions, string $role = 'reporter'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->syncRoles([]);
        $user->givePermissionTo($permissions);
        $user->projects()->attach($this->project);
        // These tests isolate action permissions on an opinion owned by the actor.
        $this->entry->update(['created_by' => $user->id]);
        $this->actingAs($user);
        return $user;
    }

    public function test_view_only_cannot_create_edit_or_delete_and_buttons_are_hidden(): void
    {
        $this->user(['ver buzon']);
        foreach ([route('buzon.index'), route('buzon.show', $this->entry)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('href="'.route('buzon.create').'"', false)
                ->assertDontSee('href="'.route('buzon.edit', $this->entry).'"', false)
                ->assertDontSee('data-buzon-delete', false);
        }
        $this->get(route('buzon.create'))->assertForbidden();
        $this->get(route('buzon.edit', $this->entry))->assertForbidden();
        $this->postJson(route('buzon.store'), $this->payload())->assertForbidden();
        $this->putJson(route('buzon.update', $this->entry), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 1])->assertForbidden();
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_create_only_can_submit_but_never_read_prior_entries_or_redirect_to_forbidden_detail(): void
    {
        $this->user(['crear buzon']);
        $this->get(route('buzon.create'))->assertOk()->assertDontSee('Contenido reservado de prueba')
            ->assertDontSee('href="'.route('buzon.index').'"', false)->assertSee('Volver al panel');
        $response = $this->post(route('buzon.store'), $this->payload(['comment' => 'Nueva opinión']))->assertSessionHasNoErrors();
        $response->assertRedirect(route('buzon.create', ['proyecto_id' => $this->project->id, 'list_id' => BuzonEntry::latest('id')->firstOrFail()->buzon_list_id]));
        $this->get(route('buzon.index'))->assertForbidden();
        $this->get(route('buzon.show', $this->entry))->assertForbidden();
        $this->get(route('buzon.edit', $this->entry))->assertForbidden();
        $this->putJson(route('buzon.update', $this->entry), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 1])->assertForbidden();
        $this->assertDatabaseCount('buzon_entries', 2);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_view_and_edit_do_not_grant_create_or_delete(): void
    {
        $this->user(['ver buzon', 'editar buzon']);
        $this->get(route('buzon.index'))->assertOk()->assertSee(route('buzon.edit', $this->entry), false)
            ->assertDontSee('data-buzon-delete', false)->assertDontSee('+ Registrar opinión');
        $this->get(route('buzon.edit', $this->entry))->assertOk();
        $this->put(route('buzon.update', $this->entry), $this->payload(['version' => 1, 'comment' => 'Corregido']))
            ->assertSessionHasNoErrors()->assertRedirect(route('buzon.show', $this->entry));
        $this->assertSame('Corregido', $this->entry->fresh()->comment);
        $this->postJson(route('buzon.store'), $this->payload())->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 2])->assertForbidden();
    }

    public function test_delete_is_independent_scoped_version_checked_and_does_not_create_beneficiaries(): void
    {
        $this->user(['ver buzon', 'eliminar buzon']);
        $foreign = BuzonEntry::create($this->payload(['proyecto_id' => $this->other->id]));
        $this->get(route('buzon.index'))->assertOk()->assertSee('data-buzon-delete', false)
            ->assertDontSee('href="'.route('buzon.edit', $this->entry).'"', false);
        $this->get(route('buzon.show', $this->entry))->assertOk()->assertSee('data-buzon-delete', false);
        $this->deleteJson(route('buzon.destroy', $foreign), ['version' => 1])->assertNotFound();
        $this->deleteJson(route('buzon.destroy', $this->entry))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->entry->update(['version' => 2]);
        $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertDatabaseCount('buzon_entries', 2);
        $this->delete(route('buzon.destroy', $this->entry), ['version' => 2])
            ->assertRedirect(route('buzon.index', ['project_ids' => [$this->project->id]]));
        $this->assertDatabaseMissing('buzon_entries', ['id' => $this->entry->id]);
        $this->assertDatabaseHas('buzon_entries', ['id' => $foreign->id]);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
        $this->assertNull(AuditLog::where('route_name', 'buzon.destroy')->latest('id')->firstOrFail()->request_payload);
    }

    public function test_edit_and_delete_require_view_even_for_administrators_and_legacy_permission_is_not_a_bypass(): void
    {
        foreach (['reporter', 'admin'] as $role) {
            $user = $this->user(['editar buzon', 'eliminar buzon', 'Buzon'], $role);
            $this->get(route('buzon.index'))->assertForbidden();
            $this->get(route('buzon.show', $this->entry))->assertForbidden();
            $this->get(route('buzon.edit', $this->entry))->assertForbidden();
            $this->get(route('buzon.create'))->assertForbidden();
            $this->postJson(route('buzon.store'), $this->payload())->assertForbidden();
            $this->putJson(route('buzon.update', $this->entry), $this->payload(['version' => 1]))->assertForbidden();
            $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 1])->assertForbidden();
            $user->syncPermissions(['Buzon']);
            $this->actingAs($user->fresh())->get(route('buzon.create'))->assertForbidden();
        }
        $this->assertDatabaseCount('buzon_entries', 1);
    }

    public function test_every_granted_action_still_requires_assigned_project_and_inactive_history_can_be_deleted(): void
    {
        $user = $this->user(['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon'], 'coordinator');
        $foreign = BuzonEntry::create($this->payload(['proyecto_id' => $this->other->id]));
        $this->get(route('buzon.show', $foreign))->assertNotFound();
        $this->get(route('buzon.edit', $foreign))->assertNotFound();
        $this->postJson(route('buzon.store'), $this->payload(['proyecto_id' => $this->other->id]))->assertUnprocessable();
        $this->putJson(route('buzon.update', $foreign), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $foreign), ['version' => 1])->assertNotFound();
        $this->project->update(['estatus' => false]);
        $this->delete(route('buzon.destroy', $this->entry), ['version' => 1])->assertRedirect();
        $this->assertDatabaseHas('buzon_entries', ['id' => $foreign->id]);
        $user->projects()->detach();
        $this->actingAs($user->fresh())->get(route('buzon.index'))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->isEmpty());
    }

    public function test_migration_preserves_direct_and_role_legacy_access_but_does_not_add_delete_to_them(): void
    {
        $direct = $this->user(['Buzon']);
        $role = Role::findOrCreate('buzon-anterior', 'web');
        $role->givePermissionTo('Buzon');
        $roleUser = User::factory()->create(['role' => 'reporter']);
        $roleUser->syncRoles([$role]);
        $outsider = User::factory()->create(['role' => 'reporter']);
        $migration = require database_path('migrations/2026_11_04_120000_add_buzon_action_permissions.php');
        $migration->up();
        $migration->up();
        foreach (['ver buzon', 'crear buzon', 'editar buzon'] as $name) {
            $this->assertTrue($direct->fresh()->can($name));
            $this->assertTrue($roleUser->fresh()->can($name));
            $this->assertFalse($outsider->fresh()->can($name));
        }
        $this->assertFalse($direct->fresh()->can('eliminar buzon'));
        $this->assertFalse($roleUser->fresh()->can('eliminar buzon'));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->can('eliminar buzon'));
        $this->assertSame(4, Permission::where('guard_name', 'web')->whereIn('name', ['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon'])->count());
        $this->assertDatabaseHas('permissions', ['name' => 'Buzon', 'guard_name' => 'web']);
    }

    public function test_permissions_are_available_in_role_management_and_a_read_only_role_cannot_write(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $names = ['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon'];
        $this->actingAs($admin)->get(route('roles.create'))->assertOk()
            ->assertViewHas('permissions', fn ($permissions) => collect($names)->diff($permissions->pluck('name'))->isEmpty());
        $permission = Permission::findByName('ver buzon', 'web');
        $this->post(route('roles.store'), ['name' => 'lector-buzon', 'permissions' => [$permission->id]])
            ->assertSessionHasNoErrors()->assertRedirect(route('roles.index'));
        $reader = User::factory()->create(['role' => 'lector-buzon']);
        $this->entry->update(['created_by' => $reader->id]);
        $reader->projects()->attach($this->project);
        $this->actingAs($reader)->get(route('buzon.index'))->assertOk()->assertSee('Ver formulario');
        $this->postJson(route('buzon.store'), $this->payload())->assertForbidden();
        $this->putJson(route('buzon.update', $this->entry), $this->payload(['version' => 1]))->assertForbidden();
        $this->deleteJson(route('buzon.destroy', $this->entry), ['version' => 1])->assertForbidden();
    }
}
