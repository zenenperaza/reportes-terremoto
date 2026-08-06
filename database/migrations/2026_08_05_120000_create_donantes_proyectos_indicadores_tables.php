<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('donantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('estatus')->default(true)->index();
            $table->string('enlaces')->nullable();
            $table->timestamps();
        });

        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donante_id')->constrained('donantes')->restrictOnDelete();
            $table->boolean('estatus')->default(true)->index();
            $table->string('codigo', 50)->unique();
            $table->string('descripcion');
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->timestamps();
        });

        Schema::create('indicadores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('descripcion');
            $table->string('unidad_conteo', 100);
            $table->string('espacio_coordinacion', 20);
            $table->string('poblacion_dirigida', 20);
            $table->timestamps();

            $table->index(['espacio_coordinacion', 'poblacion_dirigida']);
        });

        Schema::create('indicador_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('indicador_id')->constrained('indicadores')->cascadeOnDelete();
            $table->boolean('estatus')->default(true)->index();
            $table->unsignedBigInteger('meta_cuantitativa')->nullable();
            $table->text('meta_cualitativa')->nullable();
            $table->timestamps();

            $table->unique(['indicador_id', 'proyecto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicador_proyecto');
        Schema::dropIfExists('indicadores');
        Schema::dropIfExists('proyectos');
        Schema::dropIfExists('donantes');
    }
};
