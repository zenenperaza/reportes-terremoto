<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buzon_entries', function (Blueprint $table): void {
            $table->boolean('submitted_via_public_link')->default(false);
            $table->foreignId('public_recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['proyecto_id', 'public_recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::table('buzon_entries', function (Blueprint $table): void {
            $table->dropIndex(['proyecto_id', 'public_recipient_id']);
            $table->dropConstrainedForeignId('public_recipient_id');
            $table->dropColumn('submitted_via_public_link');
        });
    }
};
