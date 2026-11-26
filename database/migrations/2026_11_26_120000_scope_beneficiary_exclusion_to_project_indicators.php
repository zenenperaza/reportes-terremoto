<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicador_proyecto', function (Blueprint $table): void {
            $table->boolean('excluir_reporte_beneficiarios')->default(false);
        });

        // Preserve today's reporting behavior for every existing assignment. The
        // legacy catalog flag is retained for reference, but no longer consulted.
        DB::table('indicador_proyecto')->whereIn('indicador_id',
            DB::table('indicadores')->select('id')->where('excluir_reporte_beneficiarios', true)
        )->update(['excluir_reporte_beneficiarios' => true]);
    }

    public function down(): void
    {
        Schema::table('indicador_proyecto', function (Blueprint $table): void {
            $table->dropColumn('excluir_reporte_beneficiarios');
        });
    }
};
