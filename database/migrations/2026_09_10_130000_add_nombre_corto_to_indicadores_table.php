<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->string('nombre_corto', 150)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->dropColumn('nombre_corto');
        });
    }
};
