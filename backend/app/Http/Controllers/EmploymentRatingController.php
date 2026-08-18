<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\EmploymentRating;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Each side's verdict on a job that is over.
 *
 * Both halves live here because they are one rule seen from two ends: you may
 * rate a job you held, once it has ended, once. Splitting them across the
 * employee and pharmacist controllers would have meant writing that rule twice
 * and letting the copies drift.
 */
class EmploymentRatingController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    /** The employee's own work history, and what they still owe a verdict on. */
    public function myHistory(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $employments = Employment::query()
            ->where('employee_id', $employee->id)
            ->with(['pharmacy:id,pharmacy_name,pharmacy_address', 'ratings'])
            ->orderByDesc('started_at')
            ->get();

        return response()->json([
            'employments' => $employments->map(fn (Employment $employment) => [
                'id' => $employment->id,
                'pharmacy' => [
                    'id' => $employment->pharmacy?->id,
                    'name' => $employment->pharmacy?->pharmacy_name,
                    'address' => $employment->pharmacy?->pharmacy_address,
                ],
                'shift' => $employment->shift,
                'salary' => $employment->salary,
                'started_at' => $employment->started_at?->toISOString(),
                'ended_at' => $employment->ended_at?->toISOString(),
                'ended_by' => $employment->ended_by,
                'days' => $employment->days(),
                'can_rate' => $employment->isRateable(),
                // Their own star, so the screen shows what they already said
                // rather than inviting a duplicate.
                'my_rating' => $employment->ratings
                    ->firstWhere('direction', EmploymentRating::FROM_EMPLOYEE)?->stars,
            ])->all(),
        ]);
    }

    /** The employee rates a pharmacy they worked for. */
    public function ratePharmacy(Request $request, Employment $employment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ((int) $employment->employee_id !== (int) $employee->id) {
            return $this->notYours();
        }

        return $this->record($request, $employment, EmploymentRating::FROM_EMPLOYEE);
    }

    /** Everyone who has worked at this pharmacy, and what it still owes a verdict on. */
    public function pharmacyHistory(Request $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        $employments = Employment::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->with(['employee:id,name,role', 'ratings'])
            ->orderByDesc('started_at')
            ->get();

        return response()->json([
            'employments' => $employments->map(fn (Employment $employment) => [
                'id' => $employment->id,
                'employee' => [
                    'id' => $employment->employee?->id,
                    'name' => $employment->employee?->name,
                    'role' => $employment->employee?->role,
                ],
                'shift' => $employment->shift,
                'started_at' => $employment->started_at?->toISOString(),
                'ended_at' => $employment->ended_at?->toISOString(),
                'ended_by' => $employment->ended_by,
                'days' => $employment->days(),
                'can_rate' => $employment->isRateable(),
                'my_rating' => $employment->ratings
                    ->firstWhere('direction', EmploymentRating::FROM_PHARMACY)?->stars,
            ])->all(),
        ]);
    }

    /** The pharmacy rates someone who worked for it. */
    public function rateEmployee(Request $request, Employment $employment): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        if ((int) $employment->pharmacy_id !== (int) $pharmacy->id) {
            return $this->notYours();
        }

        return $this->record($request, $employment, EmploymentRating::FROM_PHARMACY);
    }

    /**
     * Writes one side's stars, replacing any previous verdict on the same job.
     *
     * Replacing rather than adding: a second rating is somebody changing their
     * mind, and letting both count would let either side weight the average by
     * simply repeating themselves.
     */
    private function record(Request $request, Employment $employment, string $direction): JsonResponse
    {
        $validated = $request->validate([
            'stars' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
        ]);

        if (! $employment->isRateable()) {
            return response()->json([
                'message' => 'You can rate this once the job has ended.',
                'code' => 'employment_still_running',
            ], 409);
        }

        EmploymentRating::updateOrCreate(
            ['employment_id' => $employment->id, 'direction' => $direction],
            ['stars' => $validated['stars']],
        );

        return response()->json([
            'message' => 'Thanks — your rating was recorded.',
            'code' => 'rating_recorded',
        ]);
    }

    /**
     * Indistinguishable from a job that does not exist.
     *
     * Confirming that an employment id is real would let anyone enumerate who
     * has worked where.
     */
    private function notYours(): JsonResponse
    {
        return response()->json([
            'message' => 'The requested resource was not found.',
            'code' => 'not_found',
        ], 404);
    }
}
