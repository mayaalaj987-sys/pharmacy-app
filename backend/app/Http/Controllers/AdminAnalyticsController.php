<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Platform-wide analytics for the administrator console.
 *
 * Read-only aggregates over data the platform already holds — no new tables.
 * Every figure is derived live, so nothing here can drift out of date.
 *
 * These are *platform* reports and deliberately unscoped by pharmacy, unlike
 * `ReportController`, which reports to a pharmacist about their own shop.
 */
class AdminAnalyticsController extends Controller
{
    /**
     * Employees a single pharmacy may have approved at once.
     *
     * Mirrors the cap enforced in {@see EmployeeController::approveEmployee()};
     * open vacancies are derived from it.
     */
    private const EMPLOYEE_CAP = 2;

    /** Pharmacy ownership and how the fleet is distributed across owners. */
    public function pharmacies(Request $request): JsonResponse
    {
        $this->authorizeAnalytics($request);

        // Deactivated accounts are not part of the active owner base.
        $totalOwners = Pharmacist::whereNull('deactivated_at')->count();

        $byStatus = Pharmacy::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Only approved pharmacies count as branches: a pending or rejected
        // application is not something the owner actually operates.
        $branchCounts = Pharmacy::query()
            ->where('status', 'approved')
            ->selectRaw('pharmacist_id, count(*) as branches')
            ->groupBy('pharmacist_id')
            ->pluck('branches');

        $singleBranch = $branchCounts->filter(fn ($count) => $count === 1)->count();
        $multiBranch = $branchCounts->filter(fn ($count) => $count > 1)->count();
        $operating = $singleBranch + $multiBranch;

        return response()->json([
            'data' => [
                'total_owners' => $totalOwners,
                'owners_operating' => $operating,
                'owners_without_an_approved_pharmacy' => max(0, $totalOwners - $operating),
                'branches' => [
                    'approved' => (int) ($byStatus['approved'] ?? 0),
                    'pending' => (int) ($byStatus['pending'] ?? 0),
                    'rejected' => (int) ($byStatus['rejected'] ?? 0),
                ],
                'distribution' => [
                    'single_branch_owners' => $singleBranch,
                    'multi_branch_owners' => $multiBranch,
                    'single_branch_percentage' => $this->percentage($singleBranch, $operating),
                    'multi_branch_percentage' => $this->percentage($multiBranch, $operating),
                ],
            ],
        ]);
    }

    /** Recruitment health across the platform. */
    public function jobMarket(Request $request): JsonResponse
    {
        $this->authorizeAnalytics($request);

        $byStatus = Employee::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $approved = (int) ($byStatus['approved'] ?? 0);
        $pending = (int) ($byStatus['pending'] ?? 0);
        $rejected = (int) ($byStatus['rejected'] ?? 0);
        $applicants = $approved + $pending + $rejected;

        // A vacancy is an unused slot at an approved pharmacy.
        $filledPerPharmacy = Pharmacy::query()
            ->where('status', 'approved')
            ->withCount([
                'employees as approved_employees' => fn ($query) => $query->where('status', 'approved'),
            ])
            ->pluck('approved_employees');

        $openPositions = $filledPerPharmacy
            ->map(fn ($filled) => max(0, self::EMPLOYEE_CAP - (int) $filled))
            ->sum();

        return response()->json([
            'data' => [
                'open_positions' => $openPositions,
                // Still looking: applied, not yet placed at a pharmacy.
                'active_seekers' => $pending,
                'total_applicants' => $applicants,
                'hired' => $approved,
                'rejected' => $rejected,
                'hire_rate_percentage' => $this->percentage($approved, $applicants),
                'capacity' => [
                    'employees_per_pharmacy' => self::EMPLOYEE_CAP,
                    'total_slots' => $filledPerPharmacy->count() * self::EMPLOYEE_CAP,
                    'filled_slots' => (int) $filledPerPharmacy->sum(),
                ],
            ],
        ]);
    }

    /**
     * Pharmacist sign-ups per month for the last 12 months.
     *
     * Bucketed in PHP rather than with `DATE_FORMAT`, which is MySQL-only and
     * would break on the SQLite used locally and in tests. Buckets are keyed by
     * year *and* month so the same month in different years never merges, and
     * empty months are emitted so a chart has no gaps.
     */
    public function onboarding(Request $request): JsonResponse
    {
        $this->authorizeAnalytics($request);

        $start = now()->startOfMonth()->subMonths(11);

        $buckets = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        Pharmacist::query()
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$buckets): void {
                $key = $createdAt->format('Y-m');
                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]++;
                }
            });

        $points = [];
        foreach ($buckets as $month => $registrations) {
            $points[] = [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'registrations' => $registrations,
            ];
        }

        return response()->json([
            'data' => [
                'from' => $start->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
                'total' => array_sum($buckets),
                'points' => $points,
            ],
        ]);
    }

    /**
     * Any active administrator may read analytics; the figures are aggregates
     * and expose no individual pharmacy's operating data.
     */
    private function authorizeAnalytics(Request $request): void
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);
    }

    private function percentage(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
