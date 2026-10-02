<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_proyecto_asociados', function (Blueprint $table) {
            $table->foreignId('principal_id')->constrained('indicador_proyecto')->cascadeOnDelete();
            $table->foreignId('asociado_id')->constrained('indicador_proyecto')->cascadeOnDelete();
            $table->primary(['principal_id', 'asociado_id']);
        });
        Schema::create('report_indicator_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_report_id')->constrained('reports')->cascadeOnDelete();
            // Keep the selection snapshot even if its catalog assignment or copy is deleted.
            $table->unsignedBigInteger('indicator_assignment_id');
            $table->foreignId('target_report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->unique(['source_report_id', 'indicator_assignment_id'], 'report_indicator_copy_unique');
            $table->unique('target_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_indicator_copies');
        Schema::dropIfExists('indicador_proyecto_asociados');
    }
};
