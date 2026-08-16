<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPharmacyApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->pharmacy_name,
            'address' => $this->pharmacy_address,
            'status' => $this->status,
            'submitted_at' => $this->created_at?->toISOString(),
            'review_version' => (int) $this->review_version,
            'owner' => $this->whenLoaded('pharmacist', fn () => [
                'name' => $this->pharmacist->name,
                'email' => $this->pharmacist->email,
                'phone' => $this->pharmacist->phone,
            ]),
            'documents' => $this->whenLoaded('documentVersions', fn () => AdminPharmacyDocumentResource::collection(
                $this->documentVersions->sortByDesc('version_number')->unique('document_type')->values()
            )->resolve($request)),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
        ];
    }
}
