<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Pharmacist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $role = match (true) {
            $user instanceof Pharmacist => 'pharmacist',
            $user instanceof Employee => $user->role,
            default => null,
        };

        if ($role === null || ! in_array($role, $roles, true)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}
