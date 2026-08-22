<?php

namespace App\Services;

use App\Models\Pharmacist;
use App\Models\Pharmacy;

class PharmacistApprovalService
{
    public function decision(Pharmacist $pharmacist): array
    {
        $statuses = $pharmacist->pharmacies()->pluck('status');

        if ($statuses->contains('approved')) {
            return [
                'approved' => true,
                'status' => 'approved',
                'code' => 'pharmacy_approved',
                'message' => 'Your pharmacy has been approved. You can now log in.',
            ];
        }

        if ($statuses->contains('pending')) {
            return [
                'approved' => false,
                'status' => 'pending',
                'code' => 'pharmacy_review_required',
                'message' => 'Your pharmacy registration is awaiting approval.',
            ];
        }

        if ($statuses->contains('rejected')) {
            return [
                'approved' => false,
                'status' => 'rejected',
                'code' => 'pharmacy_access_rejected',
                'message' => 'Your pharmacy registration was rejected.',
            ];
        }

        return [
            'approved' => false,
            'status' => 'no_pharmacy',
            'code' => 'no_pharmacy_available',
            'message' => 'No pharmacy is available for this account.',
        ];
    }

    public function registrationStatus(Pharmacist $pharmacist): array
    {
        $decision = $this->decision($pharmacist);

        return [
            'status' => $decision['status'],
            'code' => $decision['code'],
            'message' => $decision['message'],
            'pharmacies' => $pharmacist->pharmacies()
                ->orderBy('id')
                ->get()
                ->map(fn (Pharmacy $pharmacy) => [
                    'id' => $pharmacy->id,
                    'name' => $pharmacy->pharmacy_name,
                    'address' => $pharmacy->pharmacy_address,
                    'status' => $pharmacy->status,
                    // Only the owner sees this response, through their narrow
                    // registration-status token. Pending/approved pharmacies
                    // never receive stale decision text.
                    'rejection_reason' => $pharmacy->status === 'rejected'
                        ? $pharmacy->rejection_reason
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }
}
