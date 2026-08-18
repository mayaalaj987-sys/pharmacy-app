<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * The three wholesalers a pharmacy orders from.
 *
 * Names are drawn from Syrian geography and heritage (Barada, the river of
 * Damascus; Saidnaya in Rif Dimashq; Al-Shahba, the historic name of Aleppo)
 * so the app reads as Syrian rather than generic. They are fictional and do
 * not represent real registered businesses — the giveaway is kept in the data
 * rather than in the name: phone numbers use the Syrian mobile format
 * (09XXXXXXXX) but are not real subscriber numbers, and the `.demo` email
 * domain is deliberately unroutable.
 *
 * What each one stocks, and at what price, is in {@see CatalogueSeeding}.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'id' => 1,
                'name' => 'Barada Pharma Distribution',
                'phone' => '0930111222',
                'email' => 'orders@barada-pharma.demo',
                'address' => 'Al-Mazzeh, Damascus',
            ],
            [
                'id' => 2,
                'name' => 'Saidnaya Medical Supplies',
                'phone' => '0931222333',
                'email' => 'sales@saidnaya-medical.demo',
                'address' => 'Jaramana, Rif Dimashq',
            ],
            [
                'id' => 3,
                'name' => 'Al-Shahba Pharmaceutical Trading',
                'phone' => '0932333444',
                'email' => 'contact@alshahba-pharma.demo',
                'address' => 'Al-Furqan, Aleppo',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['id' => $supplier['id']], $supplier);
        }
    }
}
