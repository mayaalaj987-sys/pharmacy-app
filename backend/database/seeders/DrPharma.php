<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Saidnaya Medical Supplies' catalogue — Jaramana, Rif Dimashq.
 *
 * The mid-priced house, and the only one carrying the dermatology scabies line
 * and oral theophylline.
 *
 * See {@see MedicalPharma}; the drug list itself is in {@see CatalogueSeeding}.
 */
class DrPharma extends Seeder
{
    public function run(): void
    {
        CatalogueSeeding::seed(supplierId: 2);
    }
}
