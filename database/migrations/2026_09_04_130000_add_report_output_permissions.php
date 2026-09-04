<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'exportar registros excel',
        'exportar registros pdf',
        'ver detalle de registros',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions)->mapWithKeys(fn (string $name) => [
            $name => Permission::findOrCreate($name, 'web'),
        ]);

        Role::findOrCreate('admin', 'web')->givePermissionTo($permissions->values());
        foreach (['coordinator', 'reporter'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permissions['ver detalle de registros']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
