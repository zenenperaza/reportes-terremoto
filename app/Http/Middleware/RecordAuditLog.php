<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $userBeforeAction = $request->user();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->store($request, $userBeforeAction ?? $request->user(), $this->exceptionStatus($exception), $startedAt);
            throw $exception;
        }

        $this->store($request, $userBeforeAction ?? $request->user(), $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function store(Request $request, ?User $user, int $statusCode, int $startedAt): void
    {
        // Las páginas públicas anónimas no representan acciones de un usuario del sistema.
        if (! $user) {
            return;
        }

        try {
            AuditLog::query()->create([
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
                'user_email' => $user->email,
                'action' => $this->actionDescription($request),
                'method' => strtoupper($request->method()),
                'route_name' => $request->route()?->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status_code' => $statusCode,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                'request_payload' => $this->requestPayload($request),
            ]);
        } catch (Throwable $exception) {
            // La bitácora nunca debe impedir que el usuario complete su operación.
            Log::warning('No fue posible registrar una acción en la bitácora.', [
                'route' => $request->route()?->getName(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function actionDescription(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        if ($routeName === 'reports.index' && $request->filled('export_type')) {
            return 'Solicitó exportar el consolidado de registros';
        }
        $specialActions = [
            'login.store' => 'Inició sesión',
            'logout' => 'Cerró sesión',
            'password.email' => 'Solicitó recuperar su contraseña',
            'password.update' => 'Restableció su contraseña',
            'two-factor.verify' => 'Verificó el acceso de doble autenticación',
            'two-factor.resend' => 'Solicitó otro código de acceso',
            'reports.export' => 'Exportó registros',
            'beneficiaries.export' => 'Exportó el informe de beneficiarios',
            'beneficiaries.mark-reported' => 'Actualizó beneficiarios a reportados',
            'reports.review' => 'Revisó un registro',
            'backups.store' => 'Generó un respaldo',
            'backups.download' => 'Descargó un respaldo',
            'backups.destroy' => 'Eliminó un respaldo',
            'evidences.download' => 'Descargó una evidencia',
        ];

        if (isset($specialActions[$routeName])) {
            return $specialActions[$routeName];
        }

        $resource = $this->resourceLabel($routeName);
        $verb = match (strtoupper($request->method())) {
            'POST' => 'Creó o ejecutó',
            'PUT', 'PATCH' => 'Actualizó',
            'DELETE' => 'Eliminó',
            default => 'Consultó',
        };

        return trim($verb.' '.$resource);
    }

    private function resourceLabel(string $routeName): string
    {
        $prefix = explode('.', $routeName)[0] ?? '';

        return match ($prefix) {
            'dashboard' => 'el panel',
            'reports' => 'registros de actividades',
            'cases' => 'expedientes de gestión de casos',
            'families' => 'grupos familiares de gestión de casos',
            'beneficiaries' => 'beneficiarios',
            'general-reports' => 'informes generales',
            'users' => 'usuarios',
            'user-groups' => 'grupos de usuarios',
            'roles' => 'roles',
            'permissions' => 'permisos',
            'place-names' => 'lugares',
            'donantes' => 'donantes',
            'proyectos' => 'proyectos',
            'sectores' => 'sectores',
            'indicator-groups' => 'grupos de indicadores',
            'indicadores' => 'indicadores',
            'actividades' => 'actividades',
            'servicios' => 'servicios',
            'backups' => 'respaldos',
            'system-maintenance' => 'mantenimiento',
            'audit-logs' => 'la bitácora',
            'profile' => 'su perfil',
            'locations' => 'ubicaciones',
            default => $routeName !== '' ? str_replace(['-', '.'], ' ', $routeName) : 'una página del sistema',
        };
    }

    private function requestPayload(Request $request): ?array
    {
        // El historial del expediente conserva la acción y los campos modificados.
        // No duplicar datos personales ni búsquedas de casos en la bitácora general.
        if ($request->routeIs('cases.*', 'families.*')) {
            return null;
        }

        // La búsqueda del consolidado puede contener nombres, cédulas o teléfonos.
        // Registrar la consulta sin duplicar esos valores en la bitácora.
        if ($request->routeIs('reports.index') && $request->has('draw')) {
            return $request->only(['draw', 'start', 'length', 'state_id', 'from', 'to', 'reported', 'export_type']);
        }

        $data = $request->isMethod('GET') ? $request->query() : $request->all();

        if ($data === []) {
            return null;
        }

        return $this->sanitize($data);
    }

    private function sanitize(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[OCULTO]';
        }

        if ($depth >= 5) {
            return '[CONTENIDO OMITIDO]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'archivo' => $value->getClientOriginalName(),
                'tipo' => $value->getClientMimeType(),
                'tamano' => $value->getSize(),
            ];
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach (array_slice($value, 0, 100, true) as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitize($itemValue, (string) $itemKey, $depth + 1);
            }

            if (count($value) > 100) {
                $sanitized['_aviso'] = 'Se omitieron elementos adicionales.';
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 1000);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[VALOR NO SERIALIZABLE]';
    }

    private function isSensitiveKey(string $key): bool
    {
        return in_array(strtolower($key), [
            '_token', 'password', 'password_confirmation', 'current_password',
            'token', 'remember_token', 'two_factor_code', 'authorization', 'cookie',
        ], true);
    }

    private function exceptionStatus(Throwable $exception): int
    {
        return $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
    }
}
