<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateCaseResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
