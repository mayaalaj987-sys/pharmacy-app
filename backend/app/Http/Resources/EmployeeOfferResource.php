<?php

namespace App\Http\Resources;

use App\Models\Employee;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An offer as the person who received it sees it.
 *
 * Carries everything needed to weigh it up without a second request: the shift,
 * the money, where the pharmacy is, and how to reach the owner. The pharmacy
 * chose to make contact, so its owner's details are disclosed here — the
 * reverse of the pool, where an applicant's are not.
 *
 * @property-read JobOffer $resource
 */
class EmployeeOfferResource extends JsonResource
{
    public function __construct($resource, private readonly Employee $recipient)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $pharmacy = $this->resource->pharmacy;
        $owner = $pharmacy?->pharmacist;
        $reason = $this->resource->unacceptableReasonFor($this->recipient);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'shift' => $this->shift,
            'salary' => $this->salary,
            'offered_at' => $this->offered_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),

            // Derived, never stored. An offer left pending while its holder took
            // another job stays exactly as it is, so it becomes acceptable again
            // by itself the day they leave.
            'acceptable' => $reason === null,
            'unavailable_reason' => $reason,

            'pharmacy' => $pharmacy === null ? null : [
                'id' => $pharmacy->id,
                'name' => $pharmacy->pharmacy_name,
                'address' => $pharmacy->pharmacy_address,
                'latitude' => $pharmacy->latitude === null ? null : (float) $pharmacy->latitude,
                'longitude' => $pharmacy->longitude === null ? null : (float) $pharmacy->longitude,
            ],
            // Both are sent rather than one: pharmacists.phone is nullable and
            // email always exists, so "whichever they have" is resolved here
            // instead of leaving the client to guess.
            'owner' => $owner === null ? null : [
                'name' => $owner->name,
                'phone' => $owner->phone,
                'email' => $owner->email,
            ],
        ];
    }
}
