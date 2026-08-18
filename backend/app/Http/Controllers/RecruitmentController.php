<?php

namespace App\Http\Controllers;

use App\Http\Resources\PoolApplicantResource;
use App\Models\Employee;
use App\Models\JobOffer;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The hiring side of recruitment: who is looking, and what this pharmacy has
 * offered them.
 */
class RecruitmentController extends Controller
{
    private const PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    /**
     * Everyone currently looking for work.
     *
     * Filtered on `pharmacy_id`, not on `status`: the column is
     * `onDelete('set null')`, so a deleted pharmacy leaves its staff detached
     * while their status still reads 'approved'. Employment is having a
     * pharmacy; status only mirrors it.
     */
    public function pool(Request $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        $perPage = min(
            max((int) $request->integer('per_page', self::PER_PAGE), 1),
            self::MAX_PER_PAGE,
        );

        $applicants = Employee::query()
            ->whereNull('pharmacy_id')
            ->where('status', '!=', Employee::STATUS_REJECTED)
            ->with([
                // Current versions only: a recruiter should see the CV this
                // person stands behind today, not every draft they replaced.
                'documentVersions' => fn ($query) => $query->whereNull('superseded_at'),
                // Scoped to the caller's pharmacy, so one query cannot leak
                // what a competitor has offered.
                'offerFromActivePharmacy' => fn ($query) => $query->where('pharmacy_id', $pharmacy->id),
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'count' => $applicants->total(),
            'employees' => PoolApplicantResource::collection($applicants->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $applicants->currentPage(),
                'last_page' => $applicants->lastPage(),
                'per_page' => $applicants->perPage(),
                'total' => $applicants->total(),
            ],
            // What this pharmacy can actually hire for right now, so the client
            // can offer only the shifts that are open.
            'free_shifts' => $pharmacy->freeShifts(),
            'shifts' => Employee::SHIFTS,
        ]);
    }

    /** Offers this pharmacy has sent, newest first. */
    public function offers(Request $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        $offers = JobOffer::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->with('employee')
            ->orderByDesc('offered_at')
            ->get();

        return response()->json([
            'offers' => $offers->map(fn (JobOffer $offer) => [
                'id' => $offer->id,
                'status' => $offer->status,
                'shift' => $offer->shift,
                'salary' => $offer->salary,
                'offered_at' => $offer->offered_at?->toISOString(),
                'responded_at' => $offer->responded_at?->toISOString(),
                'applicant' => [
                    'id' => $offer->employee?->id,
                    'name' => $offer->employee?->name,
                    'role' => $offer->employee?->role,
                ],
                // Contact details are the reward for a yes. Until then this
                // pharmacy knows a name and a CV, and nothing else.
                'applicant_contact' => $offer->status === JobOffer::STATUS_ACCEPTED
                    ? ['phone' => $offer->employee?->phone, 'email' => $offer->employee?->email]
                    : null,
            ])->all(),
            'counts' => [
                'pending' => $offers->where('status', JobOffer::STATUS_PENDING)->count(),
                'accepted' => $offers->where('status', JobOffer::STATUS_ACCEPTED)->count(),
                'withdrawn' => $offers->where('status', JobOffer::STATUS_WITHDRAWN)->count(),
            ],
            'free_shifts' => $pharmacy->freeShifts(),
        ]);
    }
}
