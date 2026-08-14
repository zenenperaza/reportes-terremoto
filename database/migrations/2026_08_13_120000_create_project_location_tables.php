<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_proyecto', function (Blueprint $table): void {
            $table->foreignId('estado_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['estado_id', 'proyecto_id']);
        });

        Schema::create('municipio_proyecto', function (Blueprint $table): void {
            $table->foreignId('municipio_id')->constrained('municipalities')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['municipio_id', 'proyecto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipio_proyecto');
        Schema::dropIfExists('estado_proyecto');
    }
};
