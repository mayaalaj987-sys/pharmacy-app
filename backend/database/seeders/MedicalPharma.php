<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Barada Pharma Distribution's catalogue — Al-Mazzeh, Damascus.
 *
 * The widest range of the three and priced a shade above them, carrying the
 * controlled analgesic line nobody else does.
 *
 * The drugs and their prices live in {@see CatalogueSeeding}, shared with the
 * other two suppliers so the same product can be compared across them. This
 * class only says which supplier is being filled.
 */
class MedicalPharma extends Seeder
{
    public function run(): void
    {
        CatalogueSeeding::seed(supplierId: 1);
    }
}
