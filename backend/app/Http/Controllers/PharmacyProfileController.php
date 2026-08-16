<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePharmacyProfileRequest;
use App\Models\Pharmacy;
use App\Services\AuthSessionService;
use Illuminate\Http\JsonResponse;

class PharmacyProfileController extends Controller
{
    public function __construct(private readonly AuthSessionService $sessions) {}

    public function update(UpdatePharmacyProfileRequest $request): JsonResponse
    {
        /** @var Pharmacy $pharmacy */
        $pharmacy = $request->attributes->get('active_pharmacy');
        $validated = $request->validated();

        if (array_key_exists('pharmacy_name', $validated)) {
            $pharmacy->pharmacy_name = $validated['pharmacy_name'];
        }

        if (array_key_exists('pharmacy_address', $validated)) {
            $pharmacy->pharmacy_address = $validated['pharmacy_address'];
        }

        if (array_key_exists('latitude', $validated) && array_key_exists('longitude', $validated)) {
            $pharmacy->latitude = $validated['latitude'];
            $pharmacy->longitude = $validated['longitude'];
        }

        $pharmacy->save();

        return response()->json([
            'message' => 'Pharmacy profile updated successfully.',
            'code' => 'pharmacy_profile_updated',
            'data' => [
                'session' => $this->sessions->build($request),
            ],
        ]);
    }
}
