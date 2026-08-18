<?php

namespace App\Http\Resources;

use App\Models\Employee;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A job seeker as any pharmacist browsing the pool may see them.
 *
 * Deliberately narrower than {@see SafeEmployeeResource}: no phone, no email,
 * no salary, no status. That listing used to hand every approved pharmacist on
 * the platform the contact details of every applicant, before either side had
 * shown any interest in the other.
 *
 * A CV usually carries the applicant's number anyway, so this is not secrecy —
 * it removes the bulk path. A number can no longer be copied off a list of
 * everyone; it can only be reached by opening one named person's file, and
 * that is logged and reported to them.
 *
 * @property-read Employee $resource
 */
class PoolApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documents = $this->resource->relationLoaded('documentVersions')
            ? $this->resource->documentVersions
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'applied_at' => $this->created_at?->toISOString(),

            // What previous employers thought. A name and a CV say what someone
            // claims; this says how it went for the people who found out.
            'rating' => $this->resource->workRating(),

            // Availability only. The files themselves are a separate, logged
            // request; these two flags just say whether it is worth making one.
            'has_cv' => $documents->contains('document_type', 'cv'),
            'has_experience_proof' => $documents->contains('document_type', 'experience_proof'),

            // This pharmacy's own offer, so the pool can show "offer sent"
            // without a second round trip. Never another pharmacy's.
            'offer' => $this->whenLoaded('offerFromActivePharmacy', fn () => $this->formatOffer(
                $this->resource->offerFromActivePharmacy,
            )),
        ];
    }

    private function formatOffer(?JobOffer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        return [
            'id' => $offer->id,
            'status' => $offer->status,
            'shift' => $offer->shift,
            'salary' => $offer->salary,
            'offered_at' => $offer->offered_at?->toISOString(),
        ];
    }
}
