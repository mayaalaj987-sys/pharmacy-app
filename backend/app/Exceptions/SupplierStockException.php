<?php

namespace App\Exceptions;

use App\Http\Controllers\OrderController;
use App\Models\Medicine;
use RuntimeException;

/**
 * A supplier cannot fill the quantity being ordered.
 *
 * Carries the medicine rather than only a message because the client needs to
 * correct the line, and "not enough" without saying how many are left leaves
 * the pharmacist guessing.
 *
 * Deliberately not self-rendering: {@see OrderController} and the cart checkout
 * answer in different shapes — 400 against a request that named the quantity,
 * 409 against a cart that was fine when it was filled — and this stays the one
 * thing they both refuse over.
 */
class SupplierStockException extends RuntimeException
{
    public function __construct(
        public readonly Medicine $medicine,
        public readonly int $requested,
    ) {
        parent::__construct(
            'Only '.$medicine->quantity.' units of '.$medicine->name.' are available from this supplier.'
        );
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [
            'id' => $this->medicine->id,
            'name' => $this->medicine->name,
            'available_quantity' => $this->medicine->quantity,
            'requested_quantity' => $this->requested,
        ];
    }
}
