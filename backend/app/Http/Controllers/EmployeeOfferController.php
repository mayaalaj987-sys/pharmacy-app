<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeOfferResource;
use App\Models\Employee;
use App\Models\JobOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The applicant's side of recruitment.
 *
 * Outside the active-pharmacy gate on purpose: the whole point is that these
 * are readable by someone who has no pharmacy yet.
 */
class EmployeeOfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $offers = JobOffer::query()
            ->where('employee_id', $employee->id)
            ->with('pharmacy.pharmacist')
            ->orderByDesc('offered_at')
            ->get();

        $formatted = $offers
            ->map(fn (JobOffer $offer) => (new EmployeeOfferResource($offer, $employee))->resolve($request))
            ->values()
            ->all();

        return response()->json([
            'offers' => $formatted,
            'counts' => [
                // What they can act on now, which is not the same as how many
                // are pending: an offer is unacceptable while they are employed.
                'actionable' => collect($formatted)->where('acceptable', true)->count(),
                'total' => count($formatted),
            ],
            'employment' => $employee->isEmployed() ? [
                'pharmacy_id' => $employee->pharmacy_id,
                'pharmacy_name' => $employee->pharmacy?->pharmacy_name,
                'shift' => $employee->shift,
            ] : null,
        ]);
    }
}
