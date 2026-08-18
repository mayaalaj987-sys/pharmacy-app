<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\JobOffer;
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
     * Headline figures for the console dashboard.
     *
     * The month-over-month deltas are genuine, not estimates: a pharmacy's
     * `created_at` and `reviewed_at` let the same counts be reconstructed for
     * any past date. Metrics without that history (employee status, which
     * carries no timestamp) are deliberately absent rather than guessed.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeAnalytics($request);

        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $total = Pharmacy::count();
        $approved = Pharmacy::where('status', 'approved')->count();
        $pending = Pharmacy::where('status', 'pending')->count();

        // As of the end of last month. Status never flips back, so filtering on
        // the current status plus the review timestamp reconstructs the past.
        $totalThen = Pharmacy::where('created_at', '<=', $lastMonthEnd)->count();
        $approvedThen = Pharmacy::where('status', 'approved')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', $lastMonthEnd)
            ->count();
        $pendingThen = Pharmacy::where('created_at', '<=', $lastMonthEnd)
            ->where(fn ($query) => $query
                ->whereNull('reviewed_at')
                ->orWhere('reviewed_at', '>', $lastMonthEnd))
            ->count();

        return response()->json([
            'data' => [
                'compared_to' => $lastMonthEnd->toDateString(),
                'totals' => [
                    'registered' => ['value' => $total, 'change' => $this->change($total, $totalThen)],
                    'approved' => ['value' => $approved, 'change' => $this->change($approved, $approvedThen)],
                    'pending' => ['value' => $pending, 'change' => $this->change($pending, $pendingThen)],
                ],
                'pharmacies' => Pharmacy::query()
                    ->with('pharmacist:id,name')
                    ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (Pharmacy $pharmacy) => [
                        'id' => $pharmacy->id,
                        'name' => $pharmacy->pharmacy_name,
                        'address' => $pharmacy->pharmacy_address,
                        'owner' => $pharmacy->pharmacist?->name,
                        'status' => $pharmacy->status,
                    ]),
            ],
        ]);
    }

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

        $rejected = (int) ($byStatus[Employee::STATUS_REJECTED] ?? 0);

        // Counted from pharmacy_id, not from status. Employment is having a
        // pharmacy; status only mirrors it, and it lags in two directions —
        // a deleted pharmacy leaves staff detached but still reading
        // 'approved'. Reading status alone also made resignation look like an
        // un-hiring, which is not what a hire count means.
        $hired = Employee::query()->whereNotNull('pharmacy_id')->count();
        $seeking = Employee::query()
            ->whereNull('pharmacy_id')
            ->where('status', '!=', Employee::STATUS_REJECTED)
            ->count();
        $applicants = Employee::query()->count();

        // A vacancy is an uncovered shift at an approved pharmacy. Capacity is
        // the shift list, not a number: one person per shift, enforced by a
        // unique index rather than counted here.
        $cap = count(Employee::SHIFTS);
        $filledPerPharmacy = Pharmacy::query()
            ->where('status', 'approved')
            ->withCount([
                'employees as covered_shifts' => fn ($query) => $query->whereNotNull('shift'),
            ])
            ->pluck('covered_shifts');

        $openPositions = $filledPerPharmacy
            ->map(fn ($filled) => max(0, $cap - (int) $filled))
            ->sum();

        // All-time placements, which resignation cannot erode: somebody who
        // took a job and later left was still placed by this platform.
        $placements = JobOffer::where('status', JobOffer::STATUS_ACCEPTED)->count();

        return response()->json([
            'data' => [
                'open_positions' => $openPositions,
                // Still looking: applied, not yet placed at a pharmacy.
                'active_seekers' => $seeking,
                'total_applicants' => $applicants,
                'hired' => $hired,
                'rejected' => $rejected,
                'hire_rate_percentage' => $this->percentage($hired, $applicants),
                'offers' => [
                    'pending' => JobOffer::where('status', JobOffer::STATUS_PENDING)->count(),
                    'accepted_all_time' => $placements,
                ],
                'capacity' => [
                    'employees_per_pharmacy' => $cap,
                    'shifts' => Employee::SHIFTS,
                    'total_slots' => $filledPerPharmacy->count() * $cap,
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

    /**
     * Movement since a past figure.
     *
     * `percent` is null when the baseline was zero: growth from nothing has no
     * meaningful percentage, and "+100%" would overstate a single new row.
     *
     * @return array{from:int, delta:int, percent:float|null}
     */
    private function change(int $now, int $then): array
    {
        return [
            'from' => $then,
            'delta' => $now - $then,
            'percent' => $then > 0 ? round((($now - $then) / $then) * 100, 1) : null,
        ];
    }
}
