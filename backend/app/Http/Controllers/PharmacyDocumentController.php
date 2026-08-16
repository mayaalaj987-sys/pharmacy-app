<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrivateDocumentRequest;
use App\Http\Resources\PharmacyDocumentVersionResource;
use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Services\DocumentVersionService;
use App\Services\PrivateDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PharmacyDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentVersionService $versions,
        private readonly PrivateDocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $pharmacy = $this->activePharmacy($request);
        Gate::forUser($request->user())->authorize('view', $pharmacy);
        $versions = $pharmacy->documentVersions()->orderBy('document_type')->latest('version_number')->get();

        return response()->json([
            'data' => PharmacyDocumentVersionResource::collection($versions)->resolve($request),
        ]);
    }

    public function store(StorePrivateDocumentRequest $request, string $type): JsonResponse
    {
        if (! in_array($type, PharmacyDocumentVersion::TYPES, true)) {
            return response()->json(['message' => 'Unsupported document type.', 'code' => 'document_type_unsupported'], 422);
        }

        $pharmacy = $this->activePharmacy($request);
        Gate::forUser($request->user())->authorize('update', $pharmacy);
        $version = $this->versions->uploadPharmacyDocument($pharmacy, $type, $request->file('document'), $request->user());

        return response()->json([
            'message' => 'The legal document was uploaded and is awaiting review.',
            'code' => 'document_uploaded',
            'data' => (new PharmacyDocumentVersionResource($version))->resolve($request),
        ], 201);
    }

    public function download(Request $request, PharmacyDocumentVersion $document): StreamedResponse|JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $document);
        $key = $document->storage_key;
        if (! $this->documents->isOwnedStorageKey($key) || ! Storage::disk(PrivateDocumentService::DISK)->exists($key)) {
            return response()->json(['message' => 'The document is unavailable.', 'code' => 'document_unavailable'], 404);
        }

        try {
            $contents = Storage::disk(PrivateDocumentService::DISK)->get($key);
        } catch (\Throwable) {
            return response()->json(['message' => 'The document is unavailable.', 'code' => 'document_unavailable'], 404);
        }
        if (strlen($contents) !== $document->byte_size || ! hash_equals($document->sha256, hash('sha256', $contents))) {
            return response()->json(['message' => 'The document is unavailable.', 'code' => 'document_unavailable'], 404);
        }

        $extension = $document->verified_mime_type === 'application/pdf' ? 'pdf' : ($document->verified_mime_type === 'image/png' ? 'png' : 'jpg');
        $filename = sprintf('%s-v%d.%s', $document->document_type, $document->version_number, $extension);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => $document->verified_mime_type,
            'Content-Length' => (string) $document->byte_size,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function activePharmacy(Request $request): Pharmacy
    {
        return $request->attributes->get('active_pharmacy');
    }
}
