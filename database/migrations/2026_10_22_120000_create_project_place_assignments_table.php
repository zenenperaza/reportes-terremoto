<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lugar_proyecto', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('place_name_id')->constrained('place_names')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['proyecto_id', 'place_name_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lugar_proyecto');
    }
};
