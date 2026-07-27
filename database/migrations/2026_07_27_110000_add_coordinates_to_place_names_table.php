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
            $table->decimal('latitude', 10, 7)->nullable()->after('installation_type');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('altitude', 9, 2)->nullable()->after('longitude');
            $table->decimal('gps_accuracy', 8, 2)->nullable()->after('altitude');
        });

        DB::table('place_names')->orderBy('id')->each(function (object $placeName): void {
            $report = DB::table('reports')
                ->where('place_name', $placeName->name)
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->oldest('id')->first();

            if ($report) {
                DB::table('place_names')->where('id', $placeName->id)->update([
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                    'altitude' => $report->altitude,
                    'gps_accuracy' => $report->gps_accuracy,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('place_names', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'altitude', 'gps_accuracy']);
        });
    }
};
