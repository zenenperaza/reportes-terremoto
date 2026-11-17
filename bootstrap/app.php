<?php

use App\Http\Middleware\BlockDuringSystemMaintenance;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\RecordAuditLog;
use App\Http\Middleware\RunAutomaticBackup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/maintenance.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', RecordAuditLog::class);

        $middleware->alias([
            'admin' => EnsureAdministrator::class,
            'system.maintenance' => BlockDuringSystemMaintenance::class,
            'automatic.backup' => RunAutomaticBackup::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never expose stack traces, route models or server details through the public Buzón.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->routeIs('public-buzon.*') || $exception instanceof \Illuminate\Validation\ValidationException) return null;
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if ($status === 419) return null;
            $message = match ($status) {
                403, 404 => 'El enlace del Buzón no es válido o ya no está disponible. Solicite nuevamente el enlace a ASONACOP.',
                429 => 'Se han recibido demasiados intentos. Espere unos minutos antes de volver a enviar.',
                503 => 'El Buzón está temporalmente en mantenimiento. Intente más tarde.',
                default => 'No fue posible procesar el formulario. Intente más tarde.',
            };
            $headers = ($exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []) + [
                'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'X-Frame-Options' => 'DENY',
                'X-Content-Type-Options' => 'nosniff',
            ];
            return $request->expectsJson() ? response()->json(['message' => $message], $status, $headers)
                : response()->view('buzon.public.unavailable', compact('message'), $status, $headers);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->routeIs('public-buzon.*')) {
                return redirect()->to($request->fullUrl())->with('public_buzon_error',
                    'El formulario expiró. Revise los datos y vuelva a enviar su opinión.');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'La sesión expiró. Ingrese nuevamente.',
                ], 419);
            }

            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with(
                'error',
                'Su sesión expiró. Ingrese nuevamente para continuar.'
            );
        });
    })->create();
