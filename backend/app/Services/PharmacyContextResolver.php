<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PharmacyContextResolver
{
    /**
     * Resolve the tenant from the authenticated identity and reject conflicting client input.
     */
    public function resolve(Request $request, ?int $requestedPharmacyId = null): Pharmacy
    {
        $user = $request->user();
        $submittedId = $requestedPharmacyId ?? $request->integer('pharmacy_id');

        if ($user instanceof Pharmacist) {
            if ($submittedId < 1) {
                throw new AuthorizationException('A pharmacy context is required.');
            }

            $pharmacy = Pharmacy::findOrFail($submittedId);
            Gate::forUser($user)->authorize('operate', $pharmacy);

            return $pharmacy;
        }

        if ($user instanceof Employee) {
            if (! $user->pharmacy_id || ($submittedId > 0 && $submittedId !== (int) $user->pharmacy_id)) {
                throw new AuthorizationException('You cannot access this pharmacy.');
            }

            $pharmacy = Pharmacy::findOrFail($user->pharmacy_id);
            Gate::forUser($user)->authorize('operate', $pharmacy);

            return $pharmacy;
        }

        throw new AuthorizationException('Unauthenticated.');
    }

    public function owned(Request $request, int $pharmacyId, string $ability = 'operate'): Pharmacy
    {
        $pharmacy = Pharmacy::findOrFail($pharmacyId);
        Gate::forUser($request->user())->authorize($ability, $pharmacy);

        return $pharmacy;
    }
}
