<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

$maintenanceToken = static function (): string {
    $environmentFile = base_path('.env');
    $contents = is_file($environmentFile) ? (string) file_get_contents($environmentFile) : '';

    if (preg_match('/^SERVER_MAINTENANCE_TOKEN=(.*)$/m', $contents, $matches) !== 1) {
        return '';
    }

    return trim($matches[1], " \t\n\r\0\x0B\"");
};

Route::get('/ejecutar-migraciones-temp', function () use ($maintenanceToken) {
    $token = $maintenanceToken();
    abort_if($token === '', 503, 'Falta configurar SERVER_MAINTENANCE_TOKEN en el archivo .env.');
    abort_unless(hash_equals($token, (string) request('token')), 403);

    $results = [];

    try {
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $results[] = "MIGRACIONES (código {$exitCode}):";
        $results[] = Artisan::output();

        $exitCode = Artisan::call('db:seed', ['--force' => true]);
        $results[] = "SEEDERS (código {$exitCode}):";
        $results[] = Artisan::output();

        return response('<pre>'.e(implode("\n\n", $results)).'</pre>');
    } catch (\Throwable $exception) {
        return response('<pre>ERROR: '.e($exception->getMessage()).'</pre>', 500);
    }
})->name('maintenance.migrate');
