<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicine;

class MedicalPharma extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name'=>'Amoxicillin 500mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>8,'selling_price'=>12.5,'manufacturer'=>'Pfizer','quantity'=>150,'reorder_level'=>20,'expire_date'=>'2025-08-15','category_medicine'=>'Antibiotics','qr_code'=>'1111'],
            ['name'=>'Ciprofloxacin 500mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>10,'selling_price'=>15,'manufacturer'=>'Bayer','quantity'=>120,'reorder_level'=>20,'expire_date'=>'2026-01-10','category_medicine'=>'Antibiotics','qr_code'=>'1112'],
            ['name'=>'Azithromycin 250mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>12,'selling_price'=>18.5,'manufacturer'=>'Pfizer','quantity'=>158,'reorder_level'=>30,'expire_date'=>'2025-05-25','category_medicine'=>'Antibiotics','qr_code'=>'1113'],
            ['name'=>'Augmentin 625mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>14,'selling_price'=>20,'manufacturer'=>'GSK','quantity'=>90,'reorder_level'=>15,'expire_date'=>'2026-03-12','category_medicine'=>'Antibiotics','qr_code'=>'1114'],
            ['name'=>'Ibuprofen 400mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>5.5,'selling_price'=>8.99,'manufacturer'=>'Abbott','quantity'=>199,'reorder_level'=>40,'expire_date'=>'2026-03-20','category_medicine'=>'Painkillers','qr_code'=>'1115'],
            ['name'=>'Paracetamol 500mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>3,'selling_price'=>5.99,'manufacturer'=>'Sanofi','quantity'=>300,'reorder_level'=>50,'expire_date'=>'2027-01-15','category_medicine'=>'Painkillers','qr_code'=>'1116'],
            ['name'=>'Diclofenac 50mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>4,'selling_price'=>7.5,'manufacturer'=>'Novartis','quantity'=>140,'reorder_level'=>25,'expire_date'=>'2026-06-10','category_medicine'=>'Painkillers','qr_code'=>'1117'],
            ['name'=>'Aspirin 100mg','supplier_id'=>1,'pharmacy_id'=>null,'cost_price'=>2,'selling_price'=>4,'manufacturer'=>'Bayer','quantity'=>180,'reorder_level'=>30,'expire_date'=>'2027-02-01','category_medicine'=>'Painkillers','qr_code'=>'1118'],
        ];

        foreach ($data as $item) {
            Medicine::create($item);
        }
    }
}
