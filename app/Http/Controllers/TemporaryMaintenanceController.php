<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TemporaryMaintenanceController extends Controller
{
    public function __invoke(): Response
    {
        $token = $this->maintenanceToken();

        abort_if($token === '', 503, 'Falta configurar SERVER_MAINTENANCE_TOKEN en el archivo .env.');
        abort_unless(hash_equals($token, (string) request('token')), 403);

        $commands = [
            ['name' => 'optimize:clear', 'parameters' => []],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_07_29_120000_add_coordinates_to_parishes_table.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_07_29_121000_import_parish_coordinates.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_07_30_120000_add_is_active_to_users_table.php',
                '--force' => true,
            ]],
            ['name' => 'route:cache', 'parameters' => []],
            ['name' => 'view:cache', 'parameters' => []],
        ];
        $results = [];
        $exitCode = 1;

        try {
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command['name'], $command['parameters']);
                $results[] = strtoupper($command['name'])." (código {$exitCode}):";
                $results[] = trim(Artisan::output()) ?: 'Comando ejecutado sin mensajes.';

                if ($exitCode !== 0) {
                    $results[] = 'Proceso detenido porque el comando anterior no terminó correctamente.';
                    break;
                }
            }

            return response(
                '<pre>'.e(implode("\n\n", $results)).'</pre>',
                $exitCode === 0 ? 200 : 500
            );
        } catch (Throwable $exception) {
            return response('<pre>ERROR: '.e($exception->getMessage()).'</pre>', 500);
        }
    }

    private function maintenanceToken(): string
    {
        $environmentFile = base_path('.env');
        $contents = is_file($environmentFile) ? (string) file_get_contents($environmentFile) : '';

        if (preg_match('/^SERVER_MAINTENANCE_TOKEN=(.*)$/m', $contents, $matches) !== 1) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }
}
