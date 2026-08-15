<?php

namespace App\Http\Middleware;

use App\Models\Pharmacist;
use App\Services\PharmacistApprovalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePharmacistHasApprovedPharmacy
{
    public function __construct(private readonly PharmacistApprovalService $approvals) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor instanceof Pharmacist) {
            $decision = $this->approvals->decision($actor);

            if (! $decision['approved']) {
                $actor->currentAccessToken()?->delete();

                return response()->json([
                    'message' => $decision['message'],
                    'code' => $decision['code'],
                ], 403);
            }
        }

        return $next($request);
    }
}
