<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeOfferResource;
use App\Models\Employee;
use App\Models\JobOffer;
use App\Services\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The applicant's side of recruitment.
 *
 * Outside the active-pharmacy gate on purpose: the whole point is that these
 * are readable by someone who has no pharmacy yet.
 */
class EmployeeOfferController extends Controller
{
    public function __construct(private readonly RecruitmentService $recruitment) {}

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

    /**
     * Take one offer.
     *
     * The body is empty by contract: nothing about the terms is the applicant's
     * to set. Salary and shift come from the offer as it was sent, so accepting
     * cannot quietly rewrite what was agreed.
     *
     * No session is returned. Embedding one would save a round trip but bypass
     * the client path that persists the active pharmacy, leaving a stale
     * X-Pharmacy-Id in storage; the client reloads through its tested route.
     */
    public function accept(Request $request, JobOffer $offer): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        Gate::forUser($employee)->authorize('accept', $offer);

        $hired = $this->recruitment->acceptOffer($offer, $employee);

        return response()->json([
            'message' => 'You now cover the '.$hired->shift.' shift at '
                .($hired->pharmacy?->pharmacy_name ?? 'your new pharmacy').'.',
            'code' => 'offer_accepted',
        ]);
    }

    /**
     * Leave the job.
     *
     * Deliberately outside the active-pharmacy gate: somebody whose pharmacy
     * was suspended is exactly who needs to quit, and that gate would 403 them.
     */
    public function resign(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if (! $employee->isEmployed()) {
            return response()->json([
                'message' => 'You do not currently have a job to leave.',
                'code' => 'not_employed',
            ], 409);
        }

        $this->recruitment->endEmployment($employee, 'employee');

        return response()->json([
            'message' => 'You have left the job. Your offers are open again.',
            'code' => 'employment_ended',
        ]);
    }
}
