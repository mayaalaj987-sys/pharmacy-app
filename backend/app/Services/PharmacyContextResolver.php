<?php

namespace App\Services;

use App\Exceptions\PharmacyContextException;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PharmacyContextResolver
{
    /**
     * Resolve an approved operational tenant from authenticated identity.
     */
    public function resolve(Request $request, ?int $requestedPharmacyId = null): Pharmacy
    {
        $user = $request->user();
        $submittedId = $this->submittedId($request, $requestedPharmacyId);

        if ($user instanceof Pharmacist) {
            if ($submittedId === null) {
                $approved = $user->pharmacies()
                    ->where('status', 'approved')
                    ->orderBy('id')
                    ->limit(2)
                    ->get();

                if ($approved->count() === 1) {
                    return $approved->first();
                }

                if ($approved->isEmpty()) {
                    throw new PharmacyContextException(
                        'No approved pharmacy is available.',
                        'operational_access_denied',
                        403,
                    );
                }

                throw new PharmacyContextException(
                    'An active pharmacy is required.',
                    'active_pharmacy_required',
                    409,
                );
            }

            $pharmacy = Pharmacy::find($submittedId);
            if (! $pharmacy) {
                throw new PharmacyContextException(
                    'The active pharmacy is invalid.',
                    'invalid_active_pharmacy',
                    403,
                );
            }

            try {
                Gate::forUser($user)->authorize('operate', $pharmacy);
            } catch (AuthorizationException) {
                throw new PharmacyContextException(
                    'You cannot operate this pharmacy.',
                    'active_pharmacy_forbidden',
                    403,
                );
            }

            return $pharmacy;
        }

        if ($user instanceof Employee) {
            if (! $user->pharmacy_id || ($submittedId !== null && $submittedId !== (int) $user->pharmacy_id)) {
                throw new PharmacyContextException(
                    'You cannot access this pharmacy.',
                    'active_pharmacy_forbidden',
                    403,
                );
            }

            $pharmacy = Pharmacy::find($user->pharmacy_id);
            if (! $pharmacy) {
                throw new PharmacyContextException(
                    'The assigned pharmacy is unavailable.',
                    'operational_access_denied',
                    403,
                );
            }

            try {
                Gate::forUser($user)->authorize('operate', $pharmacy);
            } catch (AuthorizationException) {
                throw new PharmacyContextException(
                    'You cannot operate this pharmacy.',
                    'operational_access_denied',
                    403,
                );
            }

            return $pharmacy;
        }

        throw new PharmacyContextException('Unauthenticated.', 'unauthenticated', 401);
    }

    public function owned(Request $request, int $pharmacyId, string $ability = 'operate'): Pharmacy
    {
        $submittedId = $this->submittedId($request);
        if ($submittedId !== null && $submittedId !== $pharmacyId) {
            throw new PharmacyContextException(
                'The resource does not belong to the active pharmacy.',
                'active_pharmacy_mismatch',
                403,
            );
        }

        $pharmacy = Pharmacy::findOrFail($pharmacyId);
        Gate::forUser($request->user())->authorize($ability, $pharmacy);

        return $pharmacy;
    }

    public function assertMatches(Request $request, int $pharmacyId): Pharmacy
    {
        $active = $request->attributes->get('active_pharmacy');
        $active = $active instanceof Pharmacy ? $active : $this->resolve($request);

        if ((int) $active->id !== $pharmacyId) {
            throw new PharmacyContextException(
                'The resource does not belong to the active pharmacy.',
                'active_pharmacy_mismatch',
                403,
            );
        }

        return $active;
    }

    public function submittedId(Request $request, ?int $explicitId = null): ?int
    {
        $headerValue = $request->header('X-Pharmacy-Id');
        $inputValue = $request->input('pharmacy_id');

        $headerId = $this->normalizeId($headerValue);
        $inputId = $this->normalizeId($inputValue);

        if ($headerValue !== null && $headerId === null) {
            throw new PharmacyContextException(
                'X-Pharmacy-Id must be a positive integer.',
                'invalid_active_pharmacy',
                422,
            );
        }

        if ($inputValue !== null && $inputId === null) {
            throw new PharmacyContextException(
                'pharmacy_id must be a positive integer.',
                'invalid_active_pharmacy',
                422,
            );
        }

        if ($headerId !== null && $inputId !== null && $headerId !== $inputId) {
            throw new PharmacyContextException(
                'Submitted pharmacy contexts conflict.',
                'pharmacy_context_conflict',
                409,
            );
        }

        if ($explicitId !== null && (($headerId !== null && $headerId !== $explicitId) || ($inputId !== null && $inputId !== $explicitId))) {
            throw new PharmacyContextException(
                'Submitted pharmacy contexts conflict.',
                'pharmacy_context_conflict',
                409,
            );
        }

        return $explicitId ?? $headerId ?? $inputId;
    }

    private function normalizeId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
