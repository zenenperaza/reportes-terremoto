<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_names', function (Blueprint $table): void {
            $table->foreignId('state_id')->nullable()->after('name')->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->after('state_id')->constrained()->restrictOnDelete();
            $table->foreignId('parish_id')->nullable()->after('municipality_id')->constrained()->restrictOnDelete();
            $table->string('installation_type')->nullable()->after('parish_id');
        });

        DB::table('place_names')->orderBy('id')->each(function (object $placeName): void {
            $report = DB::table('reports')->where('place_name', $placeName->name)->oldest('id')->first();
            if (! $report) {
                return;
            }

            DB::table('place_names')->where('id', $placeName->id)->update([
                'state_id' => $report->state_id,
                'municipality_id' => $report->municipality_id,
                'parish_id' => $report->parish_id,
                'installation_type' => $report->installation_type,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('place_names', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parish_id');
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn('installation_type');
        });
    }
};
