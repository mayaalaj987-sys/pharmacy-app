<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPharmacyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->document_type,
            'review_status' => $this->review_status,
            'mime_category' => $this->verified_mime_type === 'application/pdf' ? 'pdf' : 'image',
            'size_bytes' => (int) $this->byte_size,
            'submitted_at' => $this->created_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'preview_url' => route('admin.review.documents.preview', ['pharmacy' => $this->pharmacy_id, 'document' => $this->public_id], false),
            'download_url' => route('admin.review.documents.download', ['pharmacy' => $this->pharmacy_id, 'document' => $this->public_id], false),
        ];
    }
}
