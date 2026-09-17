<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['ver casos', 'crear casos', 'editar casos', 'asignar casos', 'supervisar casos', 'ver historial de casos', 'gestionar casos vbg'];

    public function up(): void
    {
        Schema::create('case_records', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->date('registered_on');
            $table->string('case_type', 30)->default('general')->index();
            $table->string('status', 20)->default('open')->index();
            $table->string('risk_level', 20)->default('pending')->index();
            $table->string('full_name', 200)->index();
            $table->string('document_type', 60)->nullable();
            $table->string('document_number', 80)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('age_at_registration')->nullable();
            $table->string('sex', 30)->default('not_specified');
            $table->string('nationality', 100)->nullable();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained('parishes')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('phone', 60)->nullable()->index();
            $table->text('safe_contact')->nullable();
            $table->text('family_notes')->nullable();
            $table->string('support_person', 200)->nullable();
            $table->string('support_relationship', 100)->nullable();
            $table->string('support_phone', 60)->nullable();
            $table->string('consent_status', 20)->default('pending');
            $table->string('consent_source', 200)->nullable();
            $table->date('consent_date')->nullable();
            $table->text('consent_notes')->nullable();
            $table->boolean('share_services')->default(false);
            $table->boolean('share_reports')->default(false);
            $table->text('presenting_needs')->nullable();
            $table->text('immediate_actions')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['assigned_to', 'registered_on']);
        });
        Schema::create('case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 255);
            $table->string('action', 30);
            $table->json('changed_fields')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['case_record_id', 'created_at']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Role::findOrCreate('admin', 'web')->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('case_records');
        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
