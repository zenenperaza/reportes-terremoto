<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buzon_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('opinion_date')->index();
            $table->string('place_name', 200);
            $table->string('full_name', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 30)->nullable();
            $table->json('disabilities')->nullable();
            $table->boolean('afro_descendant')->nullable();
            $table->string('opinion', 20)->index();
            $table->string('feedback_type', 20)->index();
            $table->text('comment')->nullable();
            $table->text('improvement')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['proyecto_id', 'opinion_date']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('admin', 'web')->givePermissionTo(Permission::findOrCreate('Buzon', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('buzon_entries');
        Permission::where('guard_name', 'web')->where('name', 'Buzon')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
