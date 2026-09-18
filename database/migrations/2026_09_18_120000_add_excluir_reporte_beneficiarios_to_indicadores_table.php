<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->boolean('excluir_reporte_beneficiarios')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->dropColumn('excluir_reporte_beneficiarios');
        });
    }
};
