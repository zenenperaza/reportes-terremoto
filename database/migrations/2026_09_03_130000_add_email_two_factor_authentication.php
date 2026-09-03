<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('requires_two_factor')->default(false)->after('can_mark_reported');
        });

        Schema::create('email_two_factor_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_two_factor_codes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('requires_two_factor'));
    }
};
