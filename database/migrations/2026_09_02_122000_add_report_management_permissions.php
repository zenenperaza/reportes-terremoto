<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'registrar actividad',
        'manejar lugares',
        'editar registros',
        'eliminar registros',
        'solo ver registros',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions)->mapWithKeys(fn (string $name) => [
            $name => Permission::findOrCreate($name, 'web'),
        ]);

        Role::findByName('admin', 'web')->givePermissionTo($permissions->values());

        foreach (['coordinator', 'reporter'] as $roleName) {
            Role::findByName($roleName, 'web')->givePermissionTo([
                $permissions['registrar actividad'],
                $permissions['editar registros'],
                $permissions['solo ver registros'],
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
