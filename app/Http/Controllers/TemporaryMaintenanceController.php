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
            'optimize:clear',
            'route:cache',
            'view:cache',
        ];
        $results = [];

        try {
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command);
                $results[] = strtoupper($command)." (código {$exitCode}):";
                $results[] = trim(Artisan::output()) ?: 'Comando ejecutado sin mensajes.';

                if ($exitCode !== 0) {
                    break;
                }
            }

            return response(
                '<pre>'.e(implode("\n\n", $results)).'</pre>',
                ($exitCode ?? 1) === 0 ? 200 : 500
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
