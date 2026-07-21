<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@asonacop.org');
        $admin = User::withTrashed()->firstOrNew(['email' => $email]);

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->fill([
            'name' => (string) env('ADMIN_NAME', 'Administrador ASONACOP'),
            'role' => 'admin',
        ]);

        if (! $admin->exists) {
            $admin->password = Hash::make((string) env('ADMIN_PASSWORD', 'Cambiar123!'));
        }

        $admin->save();
    }
}
