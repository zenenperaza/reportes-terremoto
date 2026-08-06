<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('proyecto_user', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['proyecto_id', 'user_id']);
        });
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->nullable()->after('user_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('indicador_proyecto_id')->nullable()->after('proyecto_id')->constrained('indicador_proyecto')->restrictOnDelete();
            $table->foreignId('sector_id')->nullable()->change();
            $table->foreignId('activity_id')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('indicador_proyecto_id');
            $table->dropConstrainedForeignId('proyecto_id');
        });
        Schema::dropIfExists('proyecto_user');
    }
};
