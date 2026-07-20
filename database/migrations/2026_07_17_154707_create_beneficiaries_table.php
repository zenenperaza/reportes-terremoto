<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->unsignedTinyInteger('age');
            $table->string('sex', 50);
            $table->string('national_id', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('disability', 100);
            $table->string('ethnicity', 100);
            $table->string('pregnant_lactating', 10);
            $table->boolean('is_recurrent')->default(false);
            $table->timestamps();

            $table->index(['report_id', 'age', 'sex']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
