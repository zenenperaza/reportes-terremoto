<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('edad_desde')->default(0)->after('espacio_coordinacion');
            $table->unsignedTinyInteger('edad_hasta')->default(120)->after('edad_desde');
        });

        DB::table('indicadores')->where('poblacion_dirigida', 'NNA')->update(['edad_desde' => 0, 'edad_hasta' => 17]);
        DB::table('indicadores')->where('poblacion_dirigida', 'ADULTO')->update(['edad_desde' => 18, 'edad_hasta' => 120]);
        DB::table('indicadores')->where('poblacion_dirigida', 'AMBOS')->update(['edad_desde' => 0, 'edad_hasta' => 120]);

        Schema::table('indicadores', function (Blueprint $table): void {
            $table->dropIndex(['espacio_coordinacion', 'poblacion_dirigida']);
            $table->dropColumn('poblacion_dirigida');
            $table->index(['edad_desde', 'edad_hasta']);
        });
    }

    public function down(): void
    {
        Schema::table('indicadores', function (Blueprint $table): void {
            $table->string('poblacion_dirigida', 20)->default('AMBOS')->after('espacio_coordinacion');
        });

        DB::table('indicadores')->where('edad_desde', 0)->where('edad_hasta', 17)->update(['poblacion_dirigida' => 'NNA']);
        DB::table('indicadores')->where('edad_desde', 18)->where('edad_hasta', 120)->update(['poblacion_dirigida' => 'ADULTO']);

        Schema::table('indicadores', function (Blueprint $table): void {
            $table->dropIndex(['edad_desde', 'edad_hasta']);
            $table->dropColumn(['edad_desde', 'edad_hasta']);
            $table->index(['espacio_coordinacion', 'poblacion_dirigida']);
        });
    }
};
