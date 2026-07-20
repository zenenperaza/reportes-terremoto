<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->string('disability', 100)->nullable()->change();
            $table->string('ethnicity', 100)->nullable()->change();
            $table->string('pregnant_lactating', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('beneficiaries')->whereNull('disability')->update(['disability' => 'Ninguna']);
        DB::table('beneficiaries')->whereNull('ethnicity')->update(['ethnicity' => 'Ninguna']);
        DB::table('beneficiaries')->whereNull('pregnant_lactating')->update(['pregnant_lactating' => 'No']);

        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->string('disability', 100)->nullable(false)->change();
            $table->string('ethnicity', 100)->nullable(false)->change();
            $table->string('pregnant_lactating', 10)->nullable(false)->change();
        });
    }
};
