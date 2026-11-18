<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buzon_entries', function (Blueprint $table): void {
            $table->string('public_submission_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('buzon_entries', function (Blueprint $table): void {
            $table->dropUnique(['public_submission_key']);
            $table->dropColumn('public_submission_key');
        });
    }
};
