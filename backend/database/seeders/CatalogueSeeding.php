<?php

namespace Database\Seeders;

use App\Models\Medicine;

/**
 * Shared insert for the three supplier catalogue seeders.
 *
 * Keeps the expiry rule in one place: `months` is always counted forward from
 * the seeding date, so re-seeding an old checkout can never introduce stock
 * that is already expired.
 */
class CatalogueSeeding
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function upsert(array $item, int $supplierId): void
    {
        $months = $item['months'];
        unset($item['months']);

        Medicine::updateOrCreate(
            ['qr_code' => $item['qr_code'], 'pharmacy_id' => null],
            [
                ...$item,
                'supplier_id' => $supplierId,
                'pharmacy_id' => null,
                'expire_date' => now()->addMonths($months)->toDateString(),
            ],
        );
    }
}
