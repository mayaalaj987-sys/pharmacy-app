<?php

namespace App\Services;

use App\Exceptions\PharmacyContextException;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Http\Request;

class AuthSessionService
{
    public function __construct(
        private readonly ProfileImageService $profileImages,
        private readonly DocumentAvailabilityService $documentAvailability,
    ) {}

    public function build(Request $request, bool $honorRequestedContext = true): array
    {
        $actor = $request->user();

        if ($actor instanceof Pharmacist) {
            return $this->pharmacistSession($request, $actor, $honorRequestedContext);
        }

        if ($actor instanceof Employee) {
            return $this->employeeSession($request, $actor, $honorRequestedContext);
        }

        throw new PharmacyContextException('Unauthenticated.', 'unauthenticated', 401);
    }

    private function pharmacistSession(Request $request, Pharmacist $actor, bool $honorRequestedContext): array
    {
        $pharmacies = $actor->pharmacies()->orderBy('id')->get();
        // A suspended pharmacy keeps its approval but cannot be operated,
        // so it must not be offered as a selectable context.
        $approved = $pharmacies->filter(fn (Pharmacy $pharmacy) => $pharmacy->isOperational())->values();
        $requested = $honorRequestedContext ? $this->sessionHeaderId($request) : null;
        $active = null;
        $stale = false;

        if ($requested !== null) {
            $candidate = Pharmacy::find($requested);

            if ($candidate && (int) $candidate->pharmacist_id !== (int) $actor->id) {
                throw new PharmacyContextException(
                    'You cannot access this pharmacy.',
                    'active_pharmacy_forbidden',
                    403,
                );
            }

            if ($candidate && $candidate->isOperational()) {
                $active = $candidate;
            } else {
                $stale = true;
            }
        } elseif ($approved->count() === 1) {
            $active = $approved->first();
        }

        if ($stale) {
            $access = $this->access(false, 'stale_active_pharmacy', $approved->count() > 1);
        } elseif ($active) {
            $access = $this->access(true, 'ready', false);
        } elseif ($approved->count() > 1) {
            $access = $this->access(false, 'active_pharmacy_required', true);
        } elseif ($pharmacies->isEmpty()) {
            $access = $this->access(false, 'no_pharmacy', false);
        } else {
            $access = $this->access(false, 'pharmacy_review_required', false);
        }

        return [
            'actor' => [
                'id' => $actor->id,
                'type' => 'pharmacist',
                'role' => 'owner',
                'status' => null,
                'name' => $actor->name,
                'email' => $actor->email,
                'phone' => $actor->phone,
                'profile_image_url' => $this->profileImages->url($actor->profile_image),
            ],
            'available_pharmacies' => $pharmacies->map(fn (Pharmacy $pharmacy) => $this->pharmacy($pharmacy))->values()->all(),
            'active_pharmacy' => $active ? $this->pharmacy($active) : null,
            'access' => $access,
        ];
    }

    private function employeeSession(Request $request, Employee $actor, bool $honorRequestedContext): array
    {
        $assigned = $actor->pharmacy;
        $requested = $honorRequestedContext ? $this->sessionHeaderId($request) : null;
        $stale = false;

        if ($requested !== null && (! $assigned || $requested !== (int) $assigned->id)) {
            $candidate = Pharmacy::find($requested);
            if ($candidate && $assigned && (int) $candidate->pharmacist_id !== (int) $assigned->pharmacist_id) {
                throw new PharmacyContextException(
                    'You cannot access this pharmacy.',
                    'active_pharmacy_forbidden',
                    403,
                );
            }
            $stale = true;
        }

        $ready = ! $stale
            && $actor->status === Employee::STATUS_APPROVED
            && $assigned
            && $assigned->isOperational();

        $active = $ready ? $assigned : null;
        $code = match (true) {
            $stale => 'stale_active_pharmacy',
            $actor->status === Employee::STATUS_PENDING => 'account_pending',
            $actor->status === Employee::STATUS_REJECTED => 'account_rejected',
            ! $assigned => 'no_pharmacy',
            ! $assigned->isOperational() => 'assigned_pharmacy_unavailable',
            default => 'ready',
        };

        return [
            'actor' => [
                'id' => $actor->id,
                'type' => 'employee',
                'role' => $actor->role,
                'status' => $actor->status,
                'name' => $actor->name,
                'email' => $actor->email,
                'phone' => $actor->phone,
                // Employees have no profile_image column; the key stays so the
                // actor shape is identical for both kinds of user.
                'profile_image_url' => null,
                // One query, so the shell can badge waiting offers at sign-in
                // rather than only after the offers screen has loaded. Zero for
                // anyone already employed: none of them can be acted on.
                'pending_offer_count' => $actor->pharmacy_id === null
                    ? $actor->jobOffers()->where('status', 'pending')->count()
                    : 0,
            ],
            'available_pharmacies' => $assigned ? [$this->pharmacy($assigned)] : [],
            'active_pharmacy' => $active ? $this->pharmacy($active) : null,
            'access' => $this->access($ready, $code, false),
        ];
    }

    private function sessionHeaderId(Request $request): ?int
    {
        $value = $request->header('X-Pharmacy-Id');
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private function access(bool $operational, string $code, bool $requiresActive): array
    {
        return [
            'operational' => $operational,
            'code' => $code,
            'requires_active_pharmacy' => $requiresActive,
        ];
    }

    private function pharmacy(Pharmacy $pharmacy): array
    {
        return [
            'id' => $pharmacy->id,
            'name' => $pharmacy->pharmacy_name,
            'address' => $pharmacy->pharmacy_address,
            'latitude' => $pharmacy->latitude === null ? null : (float) $pharmacy->latitude,
            'longitude' => $pharmacy->longitude === null ? null : (float) $pharmacy->longitude,
            'status' => $pharmacy->status,
            'is_blocked' => $pharmacy->isBlocked(),
            'certificate_on_file' => $this->documentAvailability->pharmacyHas($pharmacy, 'certificate'),
            'license_on_file' => $this->documentAvailability->pharmacyHas($pharmacy, 'license'),
        ];
    }
}
