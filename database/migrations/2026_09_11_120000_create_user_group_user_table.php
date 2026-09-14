<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_group_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'user_group_id']);
            $table->index(['user_group_id', 'user_id']);
        });

        $now = now();
        $memberships = DB::table('users')
            ->whereNotNull('user_group_id')
            ->get(['id', 'user_group_id'])
            ->map(fn (object $user): array => [
                'user_id' => $user->id,
                'user_group_id' => $user->user_group_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($memberships !== []) {
            DB::table('user_group_user')->insertOrIgnore($memberships);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_group_user');
    }
};
