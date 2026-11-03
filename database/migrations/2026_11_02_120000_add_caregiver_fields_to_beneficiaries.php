<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            // Historical records remain unknown; do not infer a caregiver from the beneficiary.
            $table->string('caregiver_national_id', 30)->nullable();
            $table->string('caregiver_full_name', 150)->nullable();
            $table->string('caregiver_relationship', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', fn (Blueprint $table) => $table->dropColumn([
            'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship',
        ]));
    }
};
