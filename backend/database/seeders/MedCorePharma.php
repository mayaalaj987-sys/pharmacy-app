<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicine;

class MedCorePharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name'=>'Cetirizine 10mg','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>4,'selling_price'=>7.5,'manufacturer'=>'UCB','quantity'=>31,'reorder_level'=>10,'expire_date'=>'2025-09-30','category_medicine'=>'Respiratory','qr_code'=>'3331'],
            ['name'=>'Loratadine 10mg','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>5,'selling_price'=>8,'manufacturer'=>'Bayer','quantity'=>90,'reorder_level'=>15,'expire_date'=>'2026-02-01','category_medicine'=>'Respiratory','qr_code'=>'3332'],
            ['name'=>'Losartan 50mg','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>16,'selling_price'=>25,'manufacturer'=>'Pfizer','quantity'=>101,'reorder_level'=>20,'expire_date'=>'2026-04-15','category_medicine'=>'Cardiovascular','qr_code'=>'3333'],
            ['name'=>'Amlodipine 5mg','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>10,'selling_price'=>18,'manufacturer'=>'Novartis','quantity'=>110,'reorder_level'=>20,'expire_date'=>'2026-05-01','category_medicine'=>'Cardiovascular','qr_code'=>'3334'],
            ['name'=>'Hydrocortisone Cream','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>6.5,'selling_price'=>11,'manufacturer'=>'GSK','quantity'=>45,'reorder_level'=>10,'expire_date'=>'2025-11-20','category_medicine'=>'Dermatology','qr_code'=>'3335'],
            ['name'=>'Clotrimazole Cream','supplier_id'=>3,'pharmacy_id'=>null,'cost_price'=>5,'selling_price'=>9,'manufacturer'=>'Bayer','quantity'=>60,'reorder_level'=>10,'expire_date'=>'2026-03-01','category_medicine'=>'Dermatology','qr_code'=>'3336'],
        ];

        foreach ($data as $item) {
            Medicine::create($item);
        }
    }
}
