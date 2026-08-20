<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table): void {
            $table->boolean('estatus')->default(true)->after('descripcion')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table): void {
            $table->dropIndex(['estatus']);
            $table->dropColumn('estatus');
        });
    }
};
