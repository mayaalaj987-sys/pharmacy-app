<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Saidnaya Medical Supplies (Demo) catalogue — Jaramana, Rif Dimashq.
 *
 * See {@see MedicalPharma} for the conventions used across the demo
 * catalogues: fictional Syrian place-derived manufacturer labels, demo prices
 * in Syrian Pounds (SYP) and expiry dates always generated in the future.
 */
class DrPharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'Vitamin D3 1000IU', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Vitamins', 'cost_price' => 9000, 'selling_price' => 15000, 'quantity' => 80, 'reorder_level' => 15, 'months' => 24, 'qr_code' => '2221'],
            ['name' => 'Vitamin C 1000mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Vitamins', 'cost_price' => 6000, 'selling_price' => 10000, 'quantity' => 120, 'reorder_level' => 20, 'months' => 21, 'qr_code' => '2222'],
            ['name' => 'Multivitamin Complex', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Vitamins', 'cost_price' => 11000, 'selling_price' => 17000, 'quantity' => 95, 'reorder_level' => 15, 'months' => 27, 'qr_code' => '2223'],
            ['name' => 'Iron + Folic Acid', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Vitamins', 'cost_price' => 7000, 'selling_price' => 11500, 'quantity' => 110, 'reorder_level' => 20, 'months' => 19, 'qr_code' => '2224'],
            ['name' => 'Omeprazole 20mg', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Gastrointestinal', 'cost_price' => 6500, 'selling_price' => 11000, 'quantity' => 130, 'reorder_level' => 25, 'months' => 23, 'qr_code' => '2225'],
            ['name' => 'Domperidone 10mg', 'manufacturer' => 'Orontes Labs', 'category_medicine' => 'Gastrointestinal', 'cost_price' => 5000, 'selling_price' => 8500, 'quantity' => 100, 'reorder_level' => 20, 'months' => 17, 'qr_code' => '2226'],
            ['name' => 'Metoclopramide 10mg', 'manufacturer' => 'Ugarit Pharma', 'category_medicine' => 'Gastrointestinal', 'cost_price' => 4500, 'selling_price' => 7500, 'quantity' => 85, 'reorder_level' => 15, 'months' => 16, 'qr_code' => '2227'],
            ['name' => 'Metformin 850mg', 'manufacturer' => 'Palmyra Labs', 'category_medicine' => 'Antidiabetics', 'cost_price' => 7500, 'selling_price' => 12000, 'quantity' => 140, 'reorder_level' => 25, 'months' => 22, 'qr_code' => '2228'],
            ['name' => 'Gliclazide 80mg', 'manufacturer' => 'Qasioun Labs', 'category_medicine' => 'Antidiabetics', 'cost_price' => 8500, 'selling_price' => 13500, 'quantity' => 95, 'reorder_level' => 20, 'months' => 20, 'qr_code' => '2229'],
        ];

        foreach ($data as $item) {
            CatalogueSeeding::upsert($item, supplierId: 2);
        }
    }
}
