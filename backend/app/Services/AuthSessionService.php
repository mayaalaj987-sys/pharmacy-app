<?php

namespace App\Services;

use App\Exceptions\PharmacyContextException;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuthSessionService
{
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
        $approved = $pharmacies->where('status', 'approved')->values();
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

            if ($candidate && $candidate->status === 'approved') {
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
                'profile_image' => $this->profileImageUrl($actor->profile_image),
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
            && $actor->status === 'approved'
            && $assigned
            && $assigned->status === 'approved';

        $active = $ready ? $assigned : null;
        $code = match (true) {
            $stale => 'stale_active_pharmacy',
            $actor->status === 'pending' => 'account_pending',
            $actor->status === 'rejected' => 'account_rejected',
            ! $assigned => 'no_pharmacy',
            $assigned->status !== 'approved' => 'assigned_pharmacy_unavailable',
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
                'profile_image' => null,
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
            'status' => $pharmacy->status,
        ];
    }

    private function profileImageUrl(?string $stored): ?string
    {
        if (! $stored) {
            return null;
        }

        $decoded = json_decode($stored, true);
        $path = is_array($decoded) ? ($decoded[0] ?? null) : $stored;

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(Storage::disk('public')->url($path));
    }
}
