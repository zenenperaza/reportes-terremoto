<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->user();
        $activeUser = $authenticatedUser?->fresh();

        if ($authenticatedUser && (! $activeUser || ! $activeUser->is_active)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Su cuenta está inactiva. Contacte al Administrador.');
        }

        return $next($request);
    }
}
