<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::create([
            'id'      => 1,
            'name'    => 'Medical Pharma',
            'phone'   => '0501111111',
            'address' => 'الرياض',
            'email'   => 'medical@pharma.com',
        ]);

        Supplier::create([
            'id'      => 2,
            'name'    => 'Dr Pharma',
            'phone'   => '0502222222',
            'address' => 'جدة',
            'email'   => 'dr@pharma.com',
        ]);

        Supplier::create([
            'id'      => 3,
            'name'    => 'Med Core',
            'phone'   => '0503333333',
            'address' => 'الدمام',
            'email'   => 'medcore@pharma.com',
        ]);
    }
}
