<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_records', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('name', 200);
            $table->date('registered_on');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('restricted')->default(false);
            $table->longText('details')->nullable();
            $table->longText('members')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::table('case_records', function (Blueprint $table): void {
            $table->foreignId('family_record_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('form_data')->nullable();
        });
        Schema::create('case_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->string('path');
            $table->text('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('family_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('action', 30);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_events');
        Schema::dropIfExists('case_attachments');
        Schema::table('case_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('family_record_id');
            $table->dropColumn('form_data');
        });
        Schema::dropIfExists('family_records');
    }
};
