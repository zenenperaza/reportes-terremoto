<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'generar respaldos',
        'descargar respaldos',
        'eliminar respaldos',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions)->map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
        );

        Role::findOrCreate('admin', 'web')->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
