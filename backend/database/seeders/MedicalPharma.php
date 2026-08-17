<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Barada Pharma Distribution (Demo) catalogue — Al-Mazzeh, Damascus.
 *
 * Global supplier catalogue rows (pharmacy_id = null) that pharmacies order
 * from. Manufacturer labels are Syrian place-derived demo names (Qasioun, the
 * mountain above Damascus; Orontes, the river through Homs and Hama; Ugarit,
 * the ancient site near Latakia; Palmyra in Homs governorate) and are not real
 * registered manufacturers.
 *
 * Prices are demo values in Syrian Pounds (SYP), not official market prices.
 * Expiry dates are generated relative to the seeding date, so seeded stock is
 * never already expired.
 */
class MedicalPharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'Amoxicillin 500mg', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Antibiotics', 'cost_price' => 8000, 'selling_price' => 12500, 'quantity' => 150, 'reorder_level' => 20, 'months' => 18, 'qr_code' => '1111'],
            ['name' => 'Ciprofloxacin 500mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Antibiotics', 'cost_price' => 10000, 'selling_price' => 15000, 'quantity' => 120, 'reorder_level' => 20, 'months' => 24, 'qr_code' => '1112'],
            ['name' => 'Azithromycin 250mg', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Antibiotics', 'cost_price' => 12000, 'selling_price' => 18500, 'quantity' => 158, 'reorder_level' => 30, 'months' => 15, 'qr_code' => '1113'],
            ['name' => 'Augmentin 625mg', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Antibiotics', 'cost_price' => 14000, 'selling_price' => 20000, 'quantity' => 90, 'reorder_level' => 15, 'months' => 20, 'qr_code' => '1114'],
            ['name' => 'Ibuprofen 400mg', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Painkillers', 'cost_price' => 5500, 'selling_price' => 9000, 'quantity' => 199, 'reorder_level' => 40, 'months' => 22, 'qr_code' => '1115'],
            ['name' => 'Paracetamol 500mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Painkillers', 'cost_price' => 3000, 'selling_price' => 6000, 'quantity' => 300, 'reorder_level' => 50, 'months' => 30, 'qr_code' => '1116'],
            ['name' => 'Diclofenac 50mg', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Painkillers', 'cost_price' => 4000, 'selling_price' => 7500, 'quantity' => 140, 'reorder_level' => 25, 'months' => 26, 'qr_code' => '1117'],
            ['name' => 'Aspirin 100mg', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Painkillers', 'cost_price' => 2000, 'selling_price' => 4000, 'quantity' => 180, 'reorder_level' => 30, 'months' => 28, 'qr_code' => '1118'],
        ];

        foreach ($data as $item) {
            CatalogueSeeding::upsert($item, supplierId: 1);
        }
    }
}
