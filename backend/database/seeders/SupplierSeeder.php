<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Demo supplier catalogue for the Syrian market.
 *
 * Names are drawn from Syrian geography and heritage (Barada, the river of
 * Damascus; Saidnaya in Rif Dimashq; Al-Shahba, the historic name of Aleppo)
 * so the demo reads as Syrian rather than generic. They are fictional demo
 * records marked "(Demo)" and do not represent real registered businesses.
 *
 * Phone numbers use the Syrian mobile format (09XXXXXXXX) and are not real
 * subscriber numbers; the `.demo` email domain is deliberately unroutable.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'id' => 1,
                'name' => 'Barada Pharma Distribution (Demo)',
                'phone' => '0930111222',
                'email' => 'orders@barada-pharma.demo',
                'address' => 'Al-Mazzeh, Damascus',
            ],
            [
                'id' => 2,
                'name' => 'Saidnaya Medical Supplies (Demo)',
                'phone' => '0931222333',
                'email' => 'sales@saidnaya-medical.demo',
                'address' => 'Jaramana, Rif Dimashq',
            ],
            [
                'id' => 3,
                'name' => 'Al-Shahba Pharmaceutical Trading (Demo)',
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
