<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_roles_and_permissions(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)->post(route('permissions.store'), [
            'name' => 'exportar auditoria',
        ])->assertRedirect(route('permissions.index'));

        $permission = Permission::findByName('exportar auditoria', 'web');

        $this->actingAs($administrator)->post(route('roles.store'), [
            'name' => 'auditor',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::findByName('auditor', 'web');
        $this->assertTrue($role->hasPermissionTo($permission));

        $secondPermission = Permission::findByName('solo ver registros', 'web');
        $this->actingAs($administrator)->put(route('roles.update', $role), [
            'name' => 'auditor',
            // Los valores de los checkbox llegan como cadenas desde el navegador.
            'permissions' => [(string) $permission->id, (string) $secondPermission->id],
        ])->assertRedirect(route('roles.index'));

        $this->assertTrue($role->fresh()->hasAllPermissions([$permission, $secondPermission]));

        $this->actingAs($administrator)->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Auditor')
            ->assertSee('Exportar Auditoria');

        $this->actingAs($administrator)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Auditor');
    }

    public function test_non_administrator_cannot_manage_roles_or_permissions(): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);

        $this->actingAs($reporter)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('permissions.index'))->assertForbidden();
    }

    public function test_custom_role_permissions_are_used_by_system_authorization(): void
    {
        $role = Role::create(['name' => 'jefe de sistema', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findByName('administrar sistema', 'web'));
        $user = User::factory()->create(['role' => $role->name]);

        $this->assertTrue($user->fresh()->isAdministrator());
        $this->actingAs($user)->get(route('roles.index'))->assertOk();
    }

    public function test_view_only_role_can_consult_records_but_cannot_create_or_manage_places(): void
    {
        $role = Role::create(['name' => 'consulta', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findByName('solo ver registros', 'web'));
        $user = User::factory()->create(['role' => $role->name]);

        $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.create'))->assertForbidden();
        $this->actingAs($user)->get(route('place-names.index'))->assertForbidden();
    }
}
