<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_names', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('reports')->whereNotNull('place_name')->where('place_name', '!=', '')
            ->distinct()->orderBy('place_name')->pluck('place_name')
            ->each(fn (string $name) => DB::table('place_names')->insertOrIgnore([
                'name' => trim($name), 'created_at' => $now, 'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('place_names');
    }
};
