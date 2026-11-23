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
        $permission = Permission::findOrCreate('exportar buzon', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->where('name', 'exportar buzon')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
