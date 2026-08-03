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

        $results = [];
        $commands = [
            ['name' => 'optimize:clear', 'parameters' => []],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_03_120000_create_user_geographic_assignments.php',
                '--force' => true,
            ]],
            ['name' => 'migrate', 'parameters' => [
                '--path' => 'database/migrations/2026_08_03_130000_add_countrywide_access_to_users.php',
                '--force' => true,
            ]],
            ['name' => 'route:cache', 'parameters' => []],
            ['name' => 'view:cache', 'parameters' => []],
        ];
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
