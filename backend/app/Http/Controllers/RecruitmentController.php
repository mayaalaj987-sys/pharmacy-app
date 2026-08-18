<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendJobOfferRequest;
use App\Http\Resources\PoolApplicantResource;
use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\JobOffer;
use App\Models\RecruitmentDocumentAccess;
use App\Policies\EmployeeDocumentVersionPolicy;
use App\Services\PharmacyContextResolver;
use App\Services\PrivateDocumentService;
use App\Services\RecruitmentDocumentAccessLogger;
use App\Services\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The hiring side of recruitment: who is looking, and what this pharmacy has
 * offered them.
 */
class RecruitmentController extends Controller
{
    private const PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly PrivateDocumentService $documents,
        private readonly RecruitmentDocumentAccessLogger $accessLog,
        private readonly RecruitmentService $recruitment,
    ) {}

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

    /**
     * Offer someone a named shift.
     *
     * Replaces nothing yet — instant hiring still exists until the cutover — but
     * this is the path that asks rather than tells.
     */
    public function sendOffer(SendJobOfferRequest $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);
        $applicant = Employee::find($request->validated('employee_id'));
        $shift = $request->validated('shift');

        if ($applicant === null) {
            return response()->json([
                'message' => 'Applicant not found.',
                'code' => 'employee_not_found',
            ], 404);
        }

        if ($applicant->isEmployed()) {
            return response()->json([
                'message' => 'This applicant has already taken a job.',
                'code' => 'employee_not_available',
            ], 409);
        }

        $existing = JobOffer::where('pharmacy_id', $pharmacy->id)
            ->where('employee_id', $applicant->id)
            ->first();

        // Only while they are actually here. The accepted offer survives the
        // job ending, so this used to refuse forever: once somebody had worked
        // at a pharmacy, that pharmacy could never hire them again. Re-offering
        // reuses the row — the employment it produced is recorded separately,
        // so nothing is lost by doing so.
        if ($existing?->status === JobOffer::STATUS_ACCEPTED
            && (int) $applicant->pharmacy_id === (int) $pharmacy->id) {
            return response()->json([
                'message' => 'This person already works at your pharmacy.',
                'code' => 'offer_already_accepted',
            ], 409);
        }

        if (! in_array($shift, $pharmacy->freeShifts(), true)) {
            return response()->json([
                'message' => 'The '.$shift.' shift is already covered at this pharmacy.',
                'code' => 'shift_taken',
                'free_shifts' => $pharmacy->freeShifts(),
            ], 409);
        }

        $offer = $this->recruitment->sendOffer(
            $pharmacy,
            $applicant,
            $request->user(),
            $shift,
            $request->validated('salary') === null ? null : (float) $request->validated('salary'),
        );

        return response()->json([
            'message' => 'Offer sent to '.$applicant->name.'.',
            'code' => $existing === null ? 'offer_sent' : 'offer_updated',
            'offer' => [
                'id' => $offer->id,
                'status' => $offer->status,
                'shift' => $offer->shift,
                'salary' => $offer->salary,
                'offered_at' => $offer->offered_at?->toISOString(),
            ],
        ], $existing === null ? 201 : 200);
    }

    /** Pull an offer back. */
    public function withdrawOffer(Request $request, JobOffer $offer): JsonResponse
    {
        $this->pharmacyContext->resolve($request);
        Gate::forUser($request->user())->authorize('manage', $offer);

        if ($offer->status === JobOffer::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'This offer was already accepted and cannot be withdrawn.',
                'code' => 'offer_already_accepted',
            ], 409);
        }

        $this->recruitment->withdrawOffer($offer);

        return response()->json([
            'message' => 'The offer was withdrawn.',
            'code' => 'offer_withdrawn',
        ]);
    }

    /**
     * An applicant's current documents.
     *
     * Discharges the deferral this codebase carried in routes/api.php: employee
     * files were self-access only "until a recruitment authorization model is
     * introduced", because nobody had decided who may read whose CV and when.
     * They may now, subject to {@see EmployeeDocumentVersionPolicy::viewAsRecruiter()}.
     */
    public function applicantDocuments(Request $request, Employee $employee): JsonResponse
    {
        $this->pharmacyContext->resolve($request);

        $documents = $employee->documentVersions()
            ->whereNull('superseded_at')
            ->orderBy('document_type')
            ->get()
            ->filter(fn (EmployeeDocumentVersion $document) => Gate::forUser($request->user())
                ->allows('viewAsRecruiter', $document));

        if ($documents->isEmpty()) {
            // Indistinguishable from "this applicant does not exist", on purpose:
            // a recruiter who may not look should not learn that there is
            // something to look at.
            return response()->json([
                'message' => 'The requested resource was not found.',
                'code' => 'not_found',
            ], 404);
        }

        return response()->json([
            'data' => $documents->map(fn (EmployeeDocumentVersion $document) => [
                'id' => $document->public_id,
                'type' => $document->document_type,
                'version' => $document->version_number,
                'mime_type' => $document->verified_mime_type,
                'size_bytes' => $document->byte_size,
                'uploaded_at' => $document->created_at?->toISOString(),
                'preview_url' => route('recruitment-documents.preview', [
                    'employee' => $employee->id,
                    'document' => $document->public_id,
                ]),
                'download_url' => route('recruitment-documents.download', [
                    'employee' => $employee->id,
                    'document' => $document->public_id,
                ]),
            ])->values()->all(),
            'applicant' => ['id' => $employee->id, 'name' => $employee->name, 'role' => $employee->role],
        ]);
    }

    public function previewDocument(
        Request $request,
        Employee $employee,
        EmployeeDocumentVersion $document,
    ): StreamedResponse|JsonResponse {
        return $this->stream($request, $employee, $document, true);
    }

    public function downloadDocument(
        Request $request,
        Employee $employee,
        EmployeeDocumentVersion $document,
    ): StreamedResponse|JsonResponse {
        return $this->stream($request, $employee, $document, false);
    }

    /**
     * Serves the bytes, records who asked, and tells the applicant.
     *
     * Headers are copied from the admin document stream rather than reinvented:
     * `default-src 'none'; sandbox` and `no-referrer` mean a hostile PDF cannot
     * reach the network or report that it was opened.
     */
    private function stream(
        Request $request,
        Employee $employee,
        EmployeeDocumentVersion $document,
        bool $inline,
    ): StreamedResponse|JsonResponse {
        $pharmacy = $this->pharmacyContext->resolve($request);

        // The route uses withoutScopedBindings, so the parent link is checked
        // here — the same idiom as AdminPharmacyDocumentController.
        if ((int) $document->employee_id !== (int) $employee->id) {
            return response()->json([
                'message' => 'The requested resource was not found.',
                'code' => 'not_found',
            ], 404);
        }

        $document->setRelation('employee', $employee);
        Gate::forUser($request->user())->authorize('viewAsRecruiter', $document);

        $contents = $this->verifiedContents($document);
        if ($contents === null) {
            return response()->json([
                'message' => 'The document is unavailable.',
                'code' => 'document_unavailable',
            ], 404);
        }

        $this->accessLog->record(
            $request,
            $request->user(),
            $pharmacy,
            $document,
            $inline ? RecruitmentDocumentAccess::ACTION_PREVIEWED : RecruitmentDocumentAccess::ACTION_DOWNLOADED,
        );

        $extension = match ($document->verified_mime_type) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };

        return response()->stream(function () use ($contents): void {
            echo $contents;
        }, 200, [
            'Content-Type' => $document->verified_mime_type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                .'; filename="'.$document->document_type.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /** The stored bytes, but only if they are still exactly what was uploaded. */
    private function verifiedContents(EmployeeDocumentVersion $document): ?string
    {
        $key = $document->storage_key;

        if (! $this->documents->isOwnedStorageKey($key)) {
            return null;
        }

        try {
            $disk = Storage::disk(PrivateDocumentService::DISK);
            if (! $disk->exists($key)) {
                return null;
            }
            $contents = $disk->get($key);
        } catch (\Throwable) {
            return null;
        }

        if (strlen($contents) !== $document->byte_size
            || ! hash_equals($document->sha256, hash('sha256', $contents))) {
            return null;
        }

        return $contents;
    }
}
