<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A drug cannot be dispensed as asked.
 *
 * Two reasons, told apart because the pharmacist can act on them differently:
 * there is not enough of it across every batch on the shelf, or the only stock
 * left has expired and must not leave the pharmacy.
 *
 * Carries the drug by name rather than by row, because with batches there is no
 * single row that represents it: the shortage is a fact about the drug, not
 * about whichever batch the till happened to point at.
 */
class StockAllocationException extends RuntimeException
{
    public const NOT_ENOUGH = 'not_enough';

    public const EXPIRED = 'expired';

    private function __construct(
        public readonly string $reason,
        public readonly string $drug,
        public readonly int $available,
        public readonly ?string $expiredOn,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** Every batch together still falls short of what was asked for. */
    public static function notEnough(string $drug, int $available): self
    {
        return new self(
            self::NOT_ENOUGH,
            $drug,
            $available,
            null,
            'الكمية غير متوفرة: '.$drug,
        );
    }

    /** What is left is past its date, so none of it may be sold. */
    public static function expired(string $drug, ?string $expiredOn): self
    {
        return new self(
            self::EXPIRED,
            $drug,
            0,
            $expiredOn,
            $drug.' has expired and cannot be sold.',
        );
    }
}
