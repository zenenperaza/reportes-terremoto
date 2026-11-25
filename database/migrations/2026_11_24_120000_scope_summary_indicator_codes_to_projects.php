<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the narrower constraint before dropping the global one. Index-only
        // operations preserve IDs and child memberships, including on SQLite.
        if (! Schema::hasIndex('summary_indicators', 'summary_indicators_project_code_unique')) {
            Schema::table('summary_indicators', fn (Blueprint $table) => $table->unique(['proyecto_id', 'code'], 'summary_indicators_project_code_unique'));
        }
        if (Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique')) {
            Schema::table('summary_indicators', fn (Blueprint $table) => $table->dropUnique('summary_indicators_code_unique'));
        }
    }

    public function down(): void
    {
        if (DB::table('summary_indicators')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('No se puede restaurar la unicidad global: hay códigos de Indicadores Resumen repetidos entre proyectos. No se eliminaron ni modificaron datos.');
        }
        if (! Schema::hasIndex('summary_indicators', 'summary_indicators_code_unique')) {
            Schema::table('summary_indicators', fn (Blueprint $table) => $table->unique('code', 'summary_indicators_code_unique'));
        }
        if (Schema::hasIndex('summary_indicators', 'summary_indicators_project_code_unique')) {
            Schema::table('summary_indicators', fn (Blueprint $table) => $table->dropUnique('summary_indicators_project_code_unique'));
        }
    }
};
