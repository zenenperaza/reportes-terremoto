<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::table('indicadores', function (Blueprint $table): void {
            $table->foreignId('indicator_group_id')->nullable()->after('id')
                ->constrained('indicator_groups')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('indicator_group_id');
        });

        Schema::dropIfExists('indicator_groups');
    }
};
