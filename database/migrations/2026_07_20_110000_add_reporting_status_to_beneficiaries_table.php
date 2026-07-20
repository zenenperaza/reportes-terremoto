<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->boolean('reported')->default(false);
            $table->date('reported_at')->nullable();
            $table->index(['reported', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->dropIndex(['reported', 'reported_at']);
            $table->dropColumn(['reported', 'reported_at']);
        });
    }
};
