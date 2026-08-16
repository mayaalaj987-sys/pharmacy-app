<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOriginAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/admin', 'api/admin/*')) {
            return $next($request);
        }

        $allowed = array_values(array_filter(config('admin.allowed_origins', []), 'is_string'));
        if (app()->environment('production') && $this->hasUnsafeProductionOrigin($allowed)) {
            return response()->json([
                'message' => 'Administrator origin configuration is invalid.',
                'code' => 'admin_origin_configuration_invalid',
            ], 503);
        }

        $origin = $request->headers->get('Origin');
        if (is_string($origin) && $origin !== '' && ! in_array(rtrim($origin, '/'), array_map(fn (string $value) => rtrim($value, '/'), $allowed), true)) {
            return response()->json([
                'message' => 'This browser origin is not allowed.',
                'code' => 'origin_not_allowed',
            ], 403);
        }

        return $next($request);
    }

    /** @param array<int,string> $origins */
    private function hasUnsafeProductionOrigin(array $origins): bool
    {
        if ($origins === []) {
            return true;
        }

        foreach ($origins as $origin) {
            if (str_contains($origin, '*') || filter_var($origin, FILTER_VALIDATE_URL) === false
                || parse_url($origin, PHP_URL_SCHEME) !== 'https') {
                return true;
            }
        }

        return false;
    }
}
