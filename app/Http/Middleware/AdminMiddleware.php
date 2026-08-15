<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('admin.key', '');
        $providedKey = (string) $request->header('X-Admin-Key', '');

        // Temporary containment only. Fail closed until an admin identity system exists.
        if ($expectedKey === '' || $providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
