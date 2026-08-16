<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminRequestCorrelation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/admin', 'api/admin/*')) {
            return $next($request);
        }

        $provided = trim((string) $request->header('X-Request-ID', ''));
        $correlationId = Str::isUuid($provided) ? strtolower($provided) : (string) Str::uuid();
        $request->attributes->set('admin_correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $correlationId);

        return $response;
    }
}
