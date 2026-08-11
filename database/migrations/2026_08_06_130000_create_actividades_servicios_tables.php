<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('descripcion');
            $table->timestamps();
        });

        Schema::create('servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('actividad_indicador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicador_proyecto_id')->constrained('indicador_proyecto')->cascadeOnDelete();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->boolean('estatus')->default(true)->index();
            $table->unsignedBigInteger('meta')->nullable();
            $table->timestamps();
            $table->unique(['indicador_proyecto_id', 'actividad_id']);
        });

        Schema::create('servicio_actividad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actividad_indicador_id')->constrained('actividad_indicador')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->boolean('estatus')->default(true)->index();
            $table->unsignedBigInteger('cantidad_disponible')->nullable();
            $table->timestamps();
            $table->unique(['actividad_indicador_id', 'servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_actividad');
        Schema::dropIfExists('actividad_indicador');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('actividades');
    }
};
