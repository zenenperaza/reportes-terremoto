<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('actividad_indicador_id')->nullable()->after('indicador_proyecto_id')
                ->constrained('actividad_indicador')->restrictOnDelete();
        });

        Schema::create('report_servicio_actividad', function (Blueprint $table): void {
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('servicio_actividad_id')->constrained('servicio_actividad')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['report_id', 'servicio_actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_servicio_actividad');
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actividad_indicador_id');
        });
    }
};
