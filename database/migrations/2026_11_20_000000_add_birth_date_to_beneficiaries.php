<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', fn (Blueprint $table) => $table->dropColumn('birth_date'));
    }
};
