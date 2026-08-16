<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyDocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->document_type,
            'version' => $this->version_number,
            'mime_type' => $this->verified_mime_type,
            'size_bytes' => $this->byte_size,
            'review_status' => $this->review_status,
            'decision_reason' => $this->decision_reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'effective_at' => $this->effective_at?->toISOString(),
            'superseded_at' => $this->superseded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'download_url' => route('pharmacy-documents.download', ['document' => $this->id], false),
        ];
    }
}
