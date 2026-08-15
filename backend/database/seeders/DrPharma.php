<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicine;

class DrPharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name'=>'Vitamin D3 1000IU','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>9,'selling_price'=>15,'manufacturer'=>'NatureMade','quantity'=>80,'reorder_level'=>15,'expire_date'=>'2026-12-01','category_medicine'=>'Vitamins','qr_code'=>'2221'],
            ['name'=>'Vitamin C 1000mg','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>6,'selling_price'=>10,'manufacturer'=>'Centrum','quantity'=>120,'reorder_level'=>20,'expire_date'=>'2026-09-01','category_medicine'=>'Vitamins','qr_code'=>'2222'],
            ['name'=>'Omega 3 Capsules','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>7,'selling_price'=>13,'manufacturer'=>'NowFoods','quantity'=>70,'reorder_level'=>10,'expire_date'=>'2027-01-01','category_medicine'=>'Vitamins','qr_code'=>'2223'],
            ['name'=>'Metformin 850mg','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>14,'selling_price'=>22,'manufacturer'=>'Merck','quantity'=>30,'reorder_level'=>10,'expire_date'=>'2025-06-10','category_medicine'=>'Antidiabetics','qr_code'=>'2224'],
            ['name'=>'Glimepiride 2mg','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>11,'selling_price'=>18,'manufacturer'=>'Sanofi','quantity'=>50,'reorder_level'=>10,'expire_date'=>'2026-04-01','category_medicine'=>'Antidiabetics','qr_code'=>'2225'],
            ['name'=>'Omeprazole 20mg','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>8.5,'selling_price'=>14,'manufacturer'=>'AstraZeneca','quantity'=>120,'reorder_level'=>20,'expire_date'=>'2026-07-01','category_medicine'=>'Gastrointestinal','qr_code'=>'2226'],
            ['name'=>'Pantoprazole 40mg','supplier_id'=>2,'pharmacy_id'=>null,'cost_price'=>9,'selling_price'=>16,'manufacturer'=>'Takeda','quantity'=>100,'reorder_level'=>20,'expire_date'=>'2026-08-15','category_medicine'=>'Gastrointestinal','qr_code'=>'2227'],
        ];

        foreach ($data as $item) {
            Medicine::create($item);
        }
    }
}
