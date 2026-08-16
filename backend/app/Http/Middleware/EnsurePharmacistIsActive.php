<?php

namespace App\Http\Middleware;

use App\Models\Pharmacist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePharmacistIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor instanceof Pharmacist && $actor->deactivated_at !== null) {
            $actor->tokens()->delete();

            return response()->json([
                'message' => 'This account has been deactivated.',
                'code' => 'account_deactivated',
            ], 403);
        }

        return $next($request);
    }
}
