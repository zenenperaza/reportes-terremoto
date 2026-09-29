<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_periods', function (Blueprint $table): void {
            $table->id();
            $table->char('period', 7)->unique();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Preserve known periods without assigning dates to historical records.
        $periods = DB::table('reports')->whereNotNull('reporting_period')->distinct()->pluck('reporting_period');
        $configured = DB::table('system_settings')->where('key', 'current_period')->value('value');
        if ($configured) $periods->push($configured);
        foreach ($periods->unique() as $period) {
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                DB::table('reporting_periods')->insert(['period' => $period, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_periods');
    }
};
