<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'administrar sistema',
            'coordinar registros',
            'actualizar a reportado',
        ])->mapWithKeys(fn (string $name) => [
            $name => Permission::findOrCreate($name, 'web'),
        ]);

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions->values());
        Role::findOrCreate('coordinator', 'web')->syncPermissions([
            $permissions['coordinar registros'],
        ]);
        Role::findOrCreate('reporter', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->whereIn('name', [
            'administrar sistema',
            'coordinar registros',
            'actualizar a reportado',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
