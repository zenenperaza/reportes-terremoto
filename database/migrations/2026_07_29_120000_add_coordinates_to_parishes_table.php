<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parishes', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        DB::table('parishes')->orderBy('id')->eachById(function (object $parish): void {
            $coordinates = DB::table('place_names')
                ->where('parish_id', $parish->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->selectRaw('AVG(latitude) as latitude, AVG(longitude) as longitude')
                ->first();

            if ($coordinates?->latitude !== null && $coordinates?->longitude !== null) {
                DB::table('parishes')->where('id', $parish->id)->update([
                    'latitude' => round((float) $coordinates->latitude, 7),
                    'longitude' => round((float) $coordinates->longitude, 7),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('parishes', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
