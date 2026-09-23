<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'editar registros' => 'editar beneficiarios',
            'eliminar registros' => 'eliminar beneficiarios',
        ] as $existingName => $newName) {
            $existing = Permission::findOrCreate($existingName, 'web');
            $permission = Permission::findOrCreate($newName, 'web');

            // Keep existing assignments initially; administrators can separate them by role.
            foreach ($existing->roles as $role) {
                $role->givePermissionTo($permission);
            }
            foreach ($existing->users as $user) {
                $user->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')
            ->whereIn('name', ['editar beneficiarios', 'eliminar beneficiarios'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
