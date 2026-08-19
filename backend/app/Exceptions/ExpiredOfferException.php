<?php

namespace App\Exceptions;

use App\Models\Medicine;
use RuntimeException;

/**
 * A supplier is offering stock that has already expired.
 *
 * Refused at the point of purchase rather than at the point of sale. The POS
 * already blocks selling an expired box, so without this the money goes out,
 * the stock arrives, and the pharmacy discovers it owns something it can never
 * sell — a loss that shows up nowhere until someone reaches for the shelf.
 *
 * Only *already* expired is refused. Short-dated stock is a judgement call the
 * pharmacist is entitled to make, so the cart flags it and lets them decide.
 */
class ExpiredOfferException extends RuntimeException
{
    public function __construct(public readonly Medicine $medicine)
    {
        parent::__construct(
            $medicine->name.' from this supplier expired on '
            .$medicine->expire_date?->toDateString().' and cannot be bought.'
        );
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [
            'id' => $this->medicine->id,
            'name' => $this->medicine->name,
            'expire_date' => $this->medicine->expire_date?->toDateString(),
        ];
    }
}
