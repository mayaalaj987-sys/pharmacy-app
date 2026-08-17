<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Al-Shahba Pharmaceutical Trading (Demo) catalogue — Al-Furqan, Aleppo.
 *
 * See {@see MedicalPharma} for the conventions used across the demo
 * catalogues: fictional Syrian place-derived manufacturer labels, demo prices
 * in Syrian Pounds (SYP) and expiry dates always generated in the future.
 */
class MedCorePharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'Cetirizine 10mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Respiratory', 'cost_price' => 4000, 'selling_price' => 7500, 'quantity' => 60, 'reorder_level' => 10, 'months' => 20, 'qr_code' => '3331'],
            ['name' => 'Loratadine 10mg', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Respiratory', 'cost_price' => 5000, 'selling_price' => 8000, 'quantity' => 90, 'reorder_level' => 15, 'months' => 25, 'qr_code' => '3332'],
            ['name' => 'Salbutamol Inhaler', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Respiratory', 'cost_price' => 18000, 'selling_price' => 26000, 'quantity' => 45, 'reorder_level' => 10, 'months' => 14, 'qr_code' => '3333'],
            ['name' => 'Amlodipine 5mg', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Cardiovascular', 'cost_price' => 7000, 'selling_price' => 11000, 'quantity' => 120, 'reorder_level' => 20, 'months' => 29, 'qr_code' => '3334'],
            ['name' => 'Atorvastatin 20mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Cardiovascular', 'cost_price' => 13000, 'selling_price' => 19000, 'quantity' => 75, 'reorder_level' => 15, 'months' => 26, 'qr_code' => '3335'],
            ['name' => 'Bisoprolol 5mg', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Cardiovascular', 'cost_price' => 9000, 'selling_price' => 14000, 'quantity' => 110, 'reorder_level' => 20, 'months' => 23, 'qr_code' => '3336'],
            ['name' => 'Betamethasone Cream', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Dermatology', 'cost_price' => 6000, 'selling_price' => 9500, 'quantity' => 70, 'reorder_level' => 12, 'months' => 18, 'qr_code' => '3337'],
            ['name' => 'Clotrimazole Cream', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Dermatology', 'cost_price' => 5000, 'selling_price' => 8500, 'quantity' => 85, 'reorder_level' => 15, 'months' => 21, 'qr_code' => '3338'],
        ];

        foreach ($data as $item) {
            CatalogueSeeding::upsert($item, supplierId: 3);
        }
    }
}
