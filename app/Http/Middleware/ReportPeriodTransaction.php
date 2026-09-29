<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportPeriodTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        // Period row locks remain held until the entire mutation has finished.
        return DB::transaction(function () use ($request, $next): Response {
            $response = $next($request);
            // The routing pipeline can render an exception as a response before
            // it returns here. Such responses must not commit partial writes.
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }

            return $response;
        });
    }
}
