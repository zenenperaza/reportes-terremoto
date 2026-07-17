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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('reporter_first_name');
            $table->string('reporter_last_name');
            $table->string('reporter_email');
            $table->string('organization');
            $table->string('other_organization')->nullable();

            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('parish_id')->constrained()->restrictOnDelete();
            $table->string('installation_type');
            $table->string('place_name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('altitude', 9, 2)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();

            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->text('activity_details')->nullable();
            $table->string('recurrence_status');
            $table->unsignedInteger('total_beneficiaries');
            $table->json('beneficiary_breakdown');
            $table->unsignedInteger('people_with_disabilities')->default(0);
            $table->unsignedInteger('indigenous_people')->default(0);
            $table->unsignedInteger('pregnant_or_lactating_women')->default(0);
            $table->text('qualitative_notes')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'report_date']);
            $table->index(['state_id', 'municipality_id', 'parish_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
