<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_indicators', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('summary_indicator_assignments', function (Blueprint $table): void {
            $table->foreignId('summary_indicator_id')->constrained('summary_indicators')->cascadeOnDelete();
            $table->foreignId('indicador_proyecto_id')->constrained('indicador_proyecto')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['summary_indicator_id', 'indicador_proyecto_id'], 'summary_assignment_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_indicator_assignments');
        Schema::dropIfExists('summary_indicators');
    }
};
