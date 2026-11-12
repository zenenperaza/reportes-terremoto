<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buzon_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['proyecto_id', 'created_at']);
        });
        Schema::table('buzon_entries', function (Blueprint $table): void {
            $table->foreignId('buzon_list_id')->nullable()->constrained('buzon_lists')->restrictOnDelete();
        });
        // Historical opinions remain independent: never infer or merge old sessions.
    }

    public function down(): void
    {
        Schema::table('buzon_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('buzon_list_id'));
        Schema::dropIfExists('buzon_lists');
    }
};
