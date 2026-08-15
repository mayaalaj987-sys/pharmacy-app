<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function getAllPharmacies(): JsonResponse
    {
        return response()->json([
            'pharmacies' => Pharmacy::with('pharmacist')->get(),
        ]);
    }

    public function getPendingPharmacies(): JsonResponse
    {
        return response()->json([
            'pharmacies' => Pharmacy::where('status', 'pending')->with('pharmacist')->get(),
        ]);
    }

    public function approvePharmacy(int $id): JsonResponse
    {
        return $this->updatePendingPharmacy($id, 'approved');
    }

    public function rejectPharmacy(int $id): JsonResponse
    {
        return $this->updatePendingPharmacy($id, 'rejected');
    }

    private function updatePendingPharmacy(int $id, string $status): JsonResponse
    {
        $pharmacy = Pharmacy::find($id);

        if (! $pharmacy) {
            return response()->json(['message' => 'Pharmacy not found'], 404);
        }

        if ($pharmacy->status !== 'pending') {
            return response()->json([
                'message' => 'This pharmacy is already '.$pharmacy->status,
            ], 400);
        }

        $pharmacy->update(['status' => $status]);

        return response()->json([
            'message' => 'Pharmacy '.$status.' successfully',
            'pharmacy' => $pharmacy,
        ]);
    }
}
