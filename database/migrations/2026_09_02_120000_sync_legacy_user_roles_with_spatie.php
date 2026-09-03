<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roles = ['admin', 'coordinator', 'reporter'];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', $roles)
            ->pluck('id', 'name');

        User::query()->select(['id', 'role'])->withTrashed()->chunkById(500, function ($users) use ($roleIds): void {
            foreach ($users as $user) {
                $roleId = $roleIds->get($user->role);

                if ($roleId) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $roleId,
                        'model_type' => User::class,
                        'model_id' => $user->id,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')->where('model_type', User::class)->delete();
        DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['admin', 'coordinator', 'reporter'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
