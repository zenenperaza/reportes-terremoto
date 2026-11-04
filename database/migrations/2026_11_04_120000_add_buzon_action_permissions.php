<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['ver buzon', 'crear buzon', 'editar buzon', 'eliminar buzon'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect($this->permissions)->mapWithKeys(fn ($name) => [$name => Permission::findOrCreate($name, 'web')]);

        // Preserve the old capability without granting a new destructive action.
        // Keep the legacy permission for compatibility with older deployments;
        // the application now checks only the four action-specific permissions.
        $legacy = Permission::where('guard_name', 'web')->where('name', 'Buzon')->first();
        if ($legacy) {
            $existingActions = $permissions->except('eliminar buzon')->values();
            foreach ($legacy->roles as $role) {
                $role->givePermissionTo($existingActions);
            }
            foreach ($legacy->users as $user) {
                $user->givePermissionTo($existingActions);
            }
        }

        Role::findOrCreate('admin', 'web')->givePermissionTo($permissions->values());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
