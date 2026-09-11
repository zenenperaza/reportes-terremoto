<?php

namespace App\Http\Middleware;

use App\Services\AutomaticBackupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RunAutomaticBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->user() || app()->runningUnitTests()) {
            return;
        }

        try {
            app(AutomaticBackupService::class)->runIfDue();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
