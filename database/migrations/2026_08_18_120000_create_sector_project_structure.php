<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->unique()->after('id');
            $table->string('descripcion')->nullable()->after('codigo');
        });

        DB::table('sectors')->orderBy('id')->get()->each(function (object $sector): void {
            $codigo = strtoupper(str_replace('-', '_', $sector->slug ?: 'SEC_'.$sector->id));

            DB::table('sectors')->where('id', $sector->id)->update([
                'codigo' => mb_substr($codigo, 0, 50),
                'descripcion' => $sector->name,
            ]);
        });

        Schema::create('sector_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proyecto_id', 'sector_id']);
        });

        Schema::table('indicador_proyecto', function (Blueprint $table) {
            $table->foreignId('sector_proyecto_id')
                ->nullable()
                ->after('proyecto_id')
                ->constrained('sector_proyecto')
                ->cascadeOnDelete();

            $table->unique(['sector_proyecto_id', 'indicador_id']);
        });
    }

    public function down(): void
    {
        Schema::table('indicador_proyecto', function (Blueprint $table) {
            $table->dropUnique(['sector_proyecto_id', 'indicador_id']);
            $table->dropConstrainedForeignId('sector_proyecto_id');
        });

        Schema::dropIfExists('sector_proyecto');

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'descripcion']);
        });
    }
};
