<?php

namespace App\Http\Controllers;

use App\Exceptions\AdminWorkflowException;
use App\Http\Requests\RejectPharmacyRequest;
use App\Http\Requests\ReviewPharmacyRequest;
use App\Http\Resources\AdminPharmacyApplicationResource;
use App\Models\Pharmacy;
use App\Services\PharmacyReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminPharmacyReviewController extends Controller
{
    public function __construct(private readonly PharmacyReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);
        $paginator = Pharmacy::query()
            ->where('status', 'pending')
            ->with(['pharmacist', 'documentVersions'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json([
            'data' => AdminPharmacyApplicationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('review', $pharmacy);
        $pharmacy->load(['pharmacist', 'documentVersions']);

        return response()->json(['data' => (new AdminPharmacyApplicationResource($pharmacy))->resolve($request)]);
    }

    public function approve(ReviewPharmacyRequest $request, Pharmacy $pharmacy): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);

        return $this->decisionResponse(
            fn () => $this->reviews->approve($pharmacy, $request->user('admin'), $request->integer('review_version'), $request),
            $request,
            'pharmacy_approved',
        );
    }

    public function reject(RejectPharmacyRequest $request, Pharmacy $pharmacy): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);

        return $this->decisionResponse(
            fn () => $this->reviews->reject(
                $pharmacy,
                $request->user('admin'),
                $request->integer('review_version'),
                $request->string('reason')->toString(),
                $request,
            ),
            $request,
            'pharmacy_rejected',
        );
    }

    private function decisionResponse(callable $decision, Request $request, string $code): JsonResponse
    {
        try {
            $result = $decision();
        } catch (AdminWorkflowException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->status);
        }

        return response()->json([
            'message' => $result['idempotent'] ? 'This decision was already applied.' : 'The pharmacy review decision was applied.',
            'code' => $result['idempotent'] ? 'review_already_applied' : $code,
            'data' => (new AdminPharmacyApplicationResource($result['pharmacy']))->resolve($request),
        ]);
    }
}
