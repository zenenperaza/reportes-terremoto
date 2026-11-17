<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\PublicBuzonLink;
use Closure;
use Illuminate\Http\Request;

class PublicBuzonAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->hasValidSignature(), 403, 'El enlace del Buzón no es válido. Solicite nuevamente el enlace a ASONACOP.');
        $project = $request->route('proyecto');
        $recipient = $request->route('recipient');
        abort_unless($project && $recipient && PublicBuzonLink::available($project, $recipient)
            && $request->route('codigo') === PublicBuzonLink::code($project), 404, 'Este enlace del Buzón no está disponible.');
        abort_if(SystemSetting::maintenanceEnabled(), 503, 'El Buzón está temporalmente en mantenimiento. Intente más tarde.');

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; script-src 'self'; form-action 'self' https://asonacop.org; frame-ancestors 'none'; base-uri 'self'");

        return $response;
    }
}
