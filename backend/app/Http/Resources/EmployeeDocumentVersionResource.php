<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->document_type,
            'version' => $this->version_number,
            'mime_type' => $this->verified_mime_type,
            'size_bytes' => $this->byte_size,
            'effective_at' => $this->effective_at?->toISOString(),
            'superseded_at' => $this->superseded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'download_url' => route('employee-documents.download', ['document' => $this->id], false),
        ];
    }
}
