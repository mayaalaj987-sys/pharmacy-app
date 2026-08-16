<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\PharmacyDocumentVersion;
use App\Services\AdminAuditLogger;
use App\Services\PrivateDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPharmacyDocumentController extends Controller
{
    public function __construct(
        private readonly PrivateDocumentService $documents,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function preview(Request $request, Pharmacy $pharmacy, PharmacyDocumentVersion $document): StreamedResponse|JsonResponse
    {
        return $this->stream($request, $pharmacy, $document, true);
    }

    public function download(Request $request, Pharmacy $pharmacy, PharmacyDocumentVersion $document): StreamedResponse|JsonResponse
    {
        return $this->stream($request, $pharmacy, $document, false);
    }

    private function stream(Request $request, Pharmacy $pharmacy, PharmacyDocumentVersion $document, bool $inline): StreamedResponse|JsonResponse
    {
        if ((int) $document->pharmacy_id !== (int) $pharmacy->id) {
            return response()->json(['message' => 'The requested resource was not found.', 'code' => 'not_found'], 404);
        }
        $document->setRelation('pharmacy', $pharmacy);
        Gate::forUser($request->user('admin'))->authorize('review', $document);
        $contents = $this->documents->verifiedPharmacyDocumentContents($document);
        if ($contents === null) {
            return response()->json(['message' => 'The document is unavailable.', 'code' => 'document_unavailable'], 404);
        }

        $this->audit->record(
            $request,
            $request->user('admin'),
            $inline ? 'pharmacy.document.previewed' : 'pharmacy.document.downloaded',
            'success',
            'pharmacy_document',
            $document->id,
            after: ['document_type' => $document->document_type, 'review_status' => $document->review_status],
        );
        $extension = match ($document->verified_mime_type) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $filename = $document->document_type.'.'.$extension;

        return response()->stream(function () use ($contents): void {
            echo $contents;
        }, 200, [
            'Content-Type' => $document->verified_mime_type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
