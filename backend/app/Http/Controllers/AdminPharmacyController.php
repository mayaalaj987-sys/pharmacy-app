<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Pharmacy control: the operational register, and suspending or restoring a
 * pharmacy.
 *
 * Distinct from {@see AdminPharmacyReviewController}, which decides whether an
 * application is approved in the first place. This is what happens afterwards.
 */
class AdminPharmacyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePharmacies($request);

        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('status');

        $paginator = Pharmacy::query()
            ->with(['pharmacist:id,name,email'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner
                    ->where('pharmacy_name', 'like', '%'.$search.'%')
                    ->orWhere('pharmacy_address', 'like', '%'.$search.'%'),
            ))
            ->when($filter === 'blocked', fn ($query) => $query->whereNotNull('blocked_at'))
            ->when(
                in_array($filter, ['pending', 'approved', 'rejected'], true),
                fn ($query) => $query->where('status', $filter)->whereNull('blocked_at'),
            )
            ->orderBy('pharmacy_name')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        // Owner-level figures, fetched once rather than per row.
        $ownerIds = $paginator->getCollection()->pluck('pharmacist_id')->unique();

        $branchCounts = Pharmacy::query()
            ->whereIn('pharmacist_id', $ownerIds)
            ->where('status', 'approved')
            ->selectRaw('pharmacist_id, count(*) as total')
            ->groupBy('pharmacist_id')
            ->pluck('total', 'pharmacist_id');

        // The star rating is the *owner's rating of the app*, not a customer
        // review of the pharmacy. One per owner, revisable.
        //
        // The note comes with it. A star tells an admin somebody was unhappy
        // and nothing else; what they wrote is the only part anybody can act
        // on, and leaving it in the database unread makes asking for it a
        // waste of the owner's time.
        $ratings = Rating::query()
            ->whereIn('pharmacist_id', $ownerIds)
            ->get(['pharmacist_id', 'stars', 'note', 'date'])
            ->keyBy('pharmacist_id');

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(function (Pharmacy $pharmacy) use ($branchCounts, $ratings) {
                    // `get`, not `[]`: a Collection throws on a missing key and
                    // most owners have never rated anything.
                    $rating = $ratings->get($pharmacy->pharmacist_id);

                    return [
                        'id' => $pharmacy->id,
                        'name' => $pharmacy->pharmacy_name,
                        'address' => $pharmacy->pharmacy_address,
                        'status' => $pharmacy->status,
                        'is_blocked' => $pharmacy->isBlocked(),
                        'blocked_reason' => $pharmacy->blocked_reason,
                        'blocked_at' => $pharmacy->blocked_at?->toIso8601String(),
                        'owner' => [
                            'id' => $pharmacy->pharmacist_id,
                            'name' => $pharmacy->pharmacist?->name,
                            'email' => $pharmacy->pharmacist?->email,
                            'branches' => (int) ($branchCounts[$pharmacy->pharmacist_id] ?? 0),
                            'app_rating' => $rating ? (int) $rating->stars : null,
                            'app_rating_note' => $rating?->note,
                            'app_rated_at' => $rating?->date?->toDateString(),
                        ],
                    ];
                })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'blocked_total' => Pharmacy::whereNotNull('blocked_at')->count(),
            ],
        ]);
    }

    public function block(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $this->authorizePharmacies($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Suspending something that was never approved would be meaningless:
        // it cannot trade in the first place.
        if ($pharmacy->status !== 'approved') {
            return response()->json([
                'message' => 'Only an approved pharmacy can be suspended.',
                'code' => 'pharmacy_not_approved',
            ], 422);
        }

        $updated = DB::transaction(function () use ($pharmacy, $validated, $request): ?Pharmacy {
            $locked = Pharmacy::query()->lockForUpdate()->find($pharmacy->id);
            if ($locked === null || $locked->isBlocked()) {
                return null;
            }

            $locked->forceFill([
                'blocked_at' => now(),
                'blocked_reason' => $validated['reason'],
                'blocked_by_admin_id' => $request->user('admin')->getKey(),
            ])->save();

            return $locked;
        });

        if ($updated === null) {
            return response()->json([
                'message' => 'This pharmacy is already suspended.',
                'code' => 'pharmacy_already_blocked',
            ], 409);
        }

        return response()->json([
            'message' => 'Pharmacy suspended.',
            'code' => 'pharmacy_blocked',
        ]);
    }

    public function unblock(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $this->authorizePharmacies($request);

        if (! $pharmacy->isBlocked()) {
            return response()->json([
                'message' => 'This pharmacy is not suspended.',
                'code' => 'pharmacy_not_blocked',
            ], 409);
        }

        // Lifting a suspension restores the original approval untouched.
        $pharmacy->forceFill([
            'blocked_at' => null,
            'blocked_reason' => null,
            'blocked_by_admin_id' => null,
        ])->save();

        return response()->json([
            'message' => 'Pharmacy restored.',
            'code' => 'pharmacy_unblocked',
        ]);
    }

    private function authorizePharmacies(Request $request): void
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);
    }
}
