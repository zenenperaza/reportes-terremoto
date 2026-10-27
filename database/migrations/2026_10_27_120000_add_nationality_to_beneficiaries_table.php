<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            // Historical beneficiaries have no confirmed nationality; do not backfill it.
            $table->string('nationality', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', fn (Blueprint $table) => $table->dropColumn('nationality'));
    }
};
