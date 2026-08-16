<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin || ! Gate::forUser($admin)->allows('viewAny', Admin::class)) {
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
