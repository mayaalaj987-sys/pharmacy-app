<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Al-Shahba Pharmaceutical Trading's catalogue — Al-Furqan, Aleppo.
 *
 * The leanest prices of the three, and the only house with the cold chain for
 * insulin.
 *
 * See {@see MedicalPharma}; the drug list itself is in {@see CatalogueSeeding}.
 */
class MedCorePharma extends Seeder
{
    public function run(): void
    {
        CatalogueSeeding::seed(supplierId: 3);
    }
}
