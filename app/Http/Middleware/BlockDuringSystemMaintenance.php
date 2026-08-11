<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDuringSystemMaintenance
{
    public const MESSAGE = 'En este momento estamos realizando mantenimiento al Sistema, regrese en unos minutos.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdministrator() || $request->routeIs('logout') || ! SystemSetting::maintenanceEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->view('maintenance.system', ['message' => self::MESSAGE], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
