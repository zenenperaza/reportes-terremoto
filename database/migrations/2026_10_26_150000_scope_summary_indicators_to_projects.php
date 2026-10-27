<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Add the nullable FK inline: rebuilding this parent would cascade existing memberships.
            Schema::getConnection()->statement('ALTER TABLE summary_indicators ADD COLUMN proyecto_id INTEGER REFERENCES proyectos(id) ON DELETE RESTRICT');
            return;
        }
        Schema::table('summary_indicators', function (Blueprint $table): void {
            // Never infer ownership from old/test assignments. Administrators confirm it explicitly.
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::getConnection()->statement('ALTER TABLE summary_indicators DROP COLUMN proyecto_id');
            return;
        }
        Schema::table('summary_indicators', fn (Blueprint $table) => $table->dropConstrainedForeignId('proyecto_id'));
    }
};
