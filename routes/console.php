<?php

use App\Services\AutomaticBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:automatic', function () {
    $filename = app(AutomaticBackupService::class)->runIfDue();

    $filename
        ? $this->info("Respaldo automático {$filename} generado correctamente.")
        : $this->comment('El respaldo automático de hoy ya fue generado.');
})->purpose('Generate the daily database backup and apply the retention policy');

Schedule::command('backup:automatic')->dailyAt('02:00')->withoutOverlapping();
