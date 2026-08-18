<?php

namespace Database\Seeders;

use App\Models\Medicine;

/**
 * The one drug list every supplier is priced against.
 *
 * Written as a single master catalogue rather than three separate ones so that
 * the same drug genuinely exists at more than one supplier. That overlap is the
 * point: a pharmacy comparing suppliers needs two prices for the same product,
 * and three disjoint lists can never produce one.
 *
 * A supplier's wholesale cost is derived, not typed — {@see cost()} — so the
 * three catalogues cannot drift into a state where one supplier is uniformly
 * cheapest and the comparison stops being interesting. The suggested retail
 * price is deliberately the *same* everywhere: what a box sells for in the
 * street is a property of the drug, not of who delivered it.
 *
 * Everything here is demo data. Manufacturer labels are Syrian place-derived
 * inventions (Qasioun, the mountain above Damascus; Orontes, the river through
 * Homs and Hama; Ugarit, near Latakia; Palmyra and Zenobia; Afamia near Hama;
 * Bosra in Daraa; Mari on the Euphrates) and are not real registered
 * manufacturers. Prices are plausible Syrian Pound values, not official ones.
 * Expiry dates are counted forward from the seeding date, so re-seeding an old
 * checkout can never introduce stock that is already expired.
 */
class CatalogueSeeding
{
    /**
     * How each supplier's wholesale prices sit against the reference cost.
     *
     * Barada is the Damascus wholesaler with the widest range and charges a
     * little for it; Al-Shahba runs leaner out of Aleppo. The spread is kept
     * deliberately narrower than the per-item variation in {@see cost()}, so
     * this only tilts the odds — which supplier is actually cheapest is decided
     * drug by drug. Widen it and one house wins nearly every comparison, which
     * makes checking them pointless.
     */
    private const SUPPLIER_FACTOR = [1 => 1.01, 2 => 1.00, 3 => 0.99];

    /** Every supplier stocks a drug unless the entry says otherwise. */
    private const ALL_SUPPLIERS = [1, 2, 3];

    /** Seeds one supplier's share of the master catalogue. */
    public static function seed(int $supplierId): void
    {
        foreach (self::catalogue() as $index => $item) {
            if (! in_array($supplierId, $item['stockists'] ?? self::ALL_SUPPLIERS, true)) {
                continue;
            }

            self::upsert($item, $supplierId, $index + 1);
        }
    }

    /**
     * Writes one catalogue row, keyed on the drug and the supplier offering it.
     *
     * Keyed that way rather than on `qr_code` because the same drug now appears
     * under several suppliers: the pair is what identifies an offer, and the QR
     * code is derived from it. Re-seeding therefore updates rows in place —
     * `order_items.medicine_id` cascades on delete, so a catalogue row that is
     * replaced instead of updated would take purchase history with it.
     *
     * @param  array<string, mixed>  $item
     */
    public static function upsert(array $item, int $supplierId, int $index): void
    {
        Medicine::updateOrCreate(
            ['name' => $item['name'], 'supplier_id' => $supplierId, 'pharmacy_id' => null],
            [
                'pharmacy_id' => null,
                'category_medicine' => $item['category'],
                'manufacturer' => $item['manufacturer'],
                'cost_price' => self::cost($item['name'], $supplierId, $item['cost']),
                'selling_price' => $item['retail'],
                'reorder_level' => $item['reorder'],
                'quantity' => self::stock($item['name'], $supplierId, $item['reorder']),
                'expire_date' => now()->addMonths($item['months'])->toDateString(),
                'qr_code' => sprintf('%d%04d', $supplierId, $index),
            ],
        );
    }

    /**
     * What this supplier charges for the drug, to the nearest 100 SYP.
     *
     * Derived from the drug's name so it is stable across re-seeds, and varied
     * per supplier so no single supplier wins every comparison — the cheapest
     * source genuinely differs from one product to the next, which is what
     * makes comparing them worth doing.
     */
    private static function cost(string $name, int $supplierId, int $reference): float
    {
        $variation = (crc32($name.'|cost|'.$supplierId) % 17) - 8;  // -8% .. +8%
        $price = $reference * self::SUPPLIER_FACTOR[$supplierId] * (1 + $variation / 100);

        return round($price / 100) * 100;
    }

    /** How many boxes the supplier is holding. Varied so the three differ. */
    private static function stock(string $name, int $supplierId, int $reorder): int
    {
        return $reorder * 6 + (crc32($name.'|stock|'.$supplierId) % 60);
    }

    /**
     * The master list: one entry per drug, priced once.
     *
     * `cost` is a reference wholesale price each supplier varies around;
     * `retail` is the suggested shelf price, identical everywhere; `reorder` is
     * the level below which a pharmacy should restock, and `months` is how far
     * ahead the batch expires. `stockists` narrows a drug to the suppliers that
     * actually carry it — cold-chain insulin, controlled analgesics and the
     * pricier specialty lines are not stocked by everyone, and the app has to
     * handle a drug with only one source as readily as one with three.
     *
     * @return list<array<string, mixed>>
     */
    private static function catalogue(): array
    {
        return [
            // ---- Antibiotics ------------------------------------------------
            ['name' => 'Amoxicillin 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Qasioun Labs', 'cost' => 8000, 'retail' => 12500, 'reorder' => 20, 'months' => 18],
            ['name' => 'Ciprofloxacin 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Orontes Labs', 'cost' => 10000, 'retail' => 15000, 'reorder' => 20, 'months' => 24],
            ['name' => 'Azithromycin 250mg', 'category' => 'Antibiotics', 'manufacturer' => 'Ugarit Pharma', 'cost' => 12000, 'retail' => 18500, 'reorder' => 30, 'months' => 15],
            ['name' => 'Augmentin 625mg', 'category' => 'Antibiotics', 'manufacturer' => 'Palmyra Labs', 'cost' => 14000, 'retail' => 20000, 'reorder' => 15, 'months' => 20],
            ['name' => 'Cefixime 400mg', 'category' => 'Antibiotics', 'manufacturer' => 'Afamia Pharma', 'cost' => 15000, 'retail' => 22000, 'reorder' => 15, 'months' => 22],
            ['name' => 'Cephalexin 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Zenobia Labs', 'cost' => 9000, 'retail' => 14000, 'reorder' => 20, 'months' => 20],
            ['name' => 'Doxycycline 100mg', 'category' => 'Antibiotics', 'manufacturer' => 'Mari Pharma', 'cost' => 6500, 'retail' => 10500, 'reorder' => 20, 'months' => 26],
            ['name' => 'Clarithromycin 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Qasioun Labs', 'cost' => 16000, 'retail' => 23500, 'reorder' => 12, 'months' => 18],
            ['name' => 'Levofloxacin 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Orontes Labs', 'cost' => 17000, 'retail' => 25000, 'reorder' => 12, 'months' => 21],
            ['name' => 'Metronidazole 500mg', 'category' => 'Antibiotics', 'manufacturer' => 'Ugarit Pharma', 'cost' => 4500, 'retail' => 7500, 'reorder' => 25, 'months' => 24],
            ['name' => 'Ceftriaxone 1g Vial', 'category' => 'Antibiotics', 'manufacturer' => 'Palmyra Labs', 'cost' => 11000, 'retail' => 17000, 'reorder' => 10, 'months' => 16, 'stockists' => [1, 2]],
            ['name' => 'Amoxicillin Suspension 250mg/5ml', 'category' => 'Antibiotics', 'manufacturer' => 'Bosra Labs', 'cost' => 7000, 'retail' => 11000, 'reorder' => 25, 'months' => 14],
            ['name' => 'Clindamycin 300mg', 'category' => 'Antibiotics', 'manufacturer' => 'Afamia Pharma', 'cost' => 13000, 'retail' => 19500, 'reorder' => 12, 'months' => 20],
            ['name' => 'Nitrofurantoin 100mg', 'category' => 'Antibiotics', 'manufacturer' => 'Zenobia Labs', 'cost' => 8500, 'retail' => 13000, 'reorder' => 15, 'months' => 23],

            // ---- Painkillers ------------------------------------------------
            ['name' => 'Paracetamol 500mg', 'category' => 'Painkillers', 'manufacturer' => 'Orontes Labs', 'cost' => 3000, 'retail' => 6000, 'reorder' => 50, 'months' => 30],
            ['name' => 'Ibuprofen 400mg', 'category' => 'Painkillers', 'manufacturer' => 'Qasioun Labs', 'cost' => 5500, 'retail' => 9000, 'reorder' => 40, 'months' => 22],
            ['name' => 'Diclofenac 50mg', 'category' => 'Painkillers', 'manufacturer' => 'Ugarit Pharma', 'cost' => 4000, 'retail' => 7500, 'reorder' => 25, 'months' => 26],
            ['name' => 'Aspirin 100mg', 'category' => 'Painkillers', 'manufacturer' => 'Palmyra Labs', 'cost' => 2000, 'retail' => 4000, 'reorder' => 30, 'months' => 28],
            ['name' => 'Naproxen 500mg', 'category' => 'Painkillers', 'manufacturer' => 'Afamia Pharma', 'cost' => 6500, 'retail' => 10500, 'reorder' => 20, 'months' => 24],
            ['name' => 'Mefenamic Acid 500mg', 'category' => 'Painkillers', 'manufacturer' => 'Zenobia Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 25, 'months' => 22],
            ['name' => 'Ketoprofen 100mg', 'category' => 'Painkillers', 'manufacturer' => 'Mari Pharma', 'cost' => 7000, 'retail' => 11000, 'reorder' => 15, 'months' => 20],
            ['name' => 'Tramadol 50mg', 'category' => 'Painkillers', 'manufacturer' => 'Qasioun Labs', 'cost' => 9500, 'retail' => 15000, 'reorder' => 10, 'months' => 24, 'stockists' => [1]],
            ['name' => 'Paracetamol Syrup 120mg/5ml', 'category' => 'Painkillers', 'manufacturer' => 'Bosra Labs', 'cost' => 3500, 'retail' => 6500, 'reorder' => 40, 'months' => 18],
            ['name' => 'Ibuprofen Suspension 100mg/5ml', 'category' => 'Painkillers', 'manufacturer' => 'Orontes Labs', 'cost' => 4500, 'retail' => 8000, 'reorder' => 30, 'months' => 16],
            ['name' => 'Celecoxib 200mg', 'category' => 'Painkillers', 'manufacturer' => 'Ugarit Pharma', 'cost' => 12000, 'retail' => 18000, 'reorder' => 12, 'months' => 25],
            ['name' => 'Piroxicam 20mg', 'category' => 'Painkillers', 'manufacturer' => 'Palmyra Labs', 'cost' => 6000, 'retail' => 9500, 'reorder' => 15, 'months' => 23],
            ['name' => 'Diclofenac Gel 1%', 'category' => 'Painkillers', 'manufacturer' => 'Afamia Pharma', 'cost' => 5500, 'retail' => 9500, 'reorder' => 20, 'months' => 21],
            ['name' => 'Paracetamol 1g Effervescent', 'category' => 'Painkillers', 'manufacturer' => 'Zenobia Labs', 'cost' => 4000, 'retail' => 7000, 'reorder' => 30, 'months' => 19],

            // ---- Vitamins ---------------------------------------------------
            ['name' => 'Vitamin D3 1000IU', 'category' => 'Vitamins', 'manufacturer' => 'Qasioun Labs', 'cost' => 9000, 'retail' => 15000, 'reorder' => 15, 'months' => 24],
            ['name' => 'Vitamin C 1000mg', 'category' => 'Vitamins', 'manufacturer' => 'Orontes Labs', 'cost' => 6000, 'retail' => 10000, 'reorder' => 20, 'months' => 21],
            ['name' => 'Multivitamin Complex', 'category' => 'Vitamins', 'manufacturer' => 'Ugarit Pharma', 'cost' => 11000, 'retail' => 17000, 'reorder' => 15, 'months' => 27],
            ['name' => 'Iron + Folic Acid', 'category' => 'Vitamins', 'manufacturer' => 'Palmyra Labs', 'cost' => 7000, 'retail' => 11500, 'reorder' => 20, 'months' => 19],
            ['name' => 'Vitamin B12 1000mcg', 'category' => 'Vitamins', 'manufacturer' => 'Afamia Pharma', 'cost' => 8000, 'retail' => 13000, 'reorder' => 15, 'months' => 25],
            ['name' => 'Vitamin B Complex', 'category' => 'Vitamins', 'manufacturer' => 'Zenobia Labs', 'cost' => 7500, 'retail' => 12000, 'reorder' => 18, 'months' => 23],
            ['name' => 'Calcium + Vitamin D3', 'category' => 'Vitamins', 'manufacturer' => 'Mari Pharma', 'cost' => 9500, 'retail' => 15500, 'reorder' => 20, 'months' => 26],
            ['name' => 'Folic Acid 5mg', 'category' => 'Vitamins', 'manufacturer' => 'Bosra Labs', 'cost' => 3500, 'retail' => 6000, 'reorder' => 25, 'months' => 22],
            ['name' => 'Zinc 50mg', 'category' => 'Vitamins', 'manufacturer' => 'Qasioun Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 20, 'months' => 28],
            ['name' => 'Omega-3 1000mg', 'category' => 'Vitamins', 'manufacturer' => 'Orontes Labs', 'cost' => 13000, 'retail' => 20000, 'reorder' => 12, 'months' => 20],
            ['name' => 'Magnesium 400mg', 'category' => 'Vitamins', 'manufacturer' => 'Ugarit Pharma', 'cost' => 8500, 'retail' => 13500, 'reorder' => 15, 'months' => 24],
            ['name' => 'Vitamin E 400IU', 'category' => 'Vitamins', 'manufacturer' => 'Palmyra Labs', 'cost' => 7000, 'retail' => 11500, 'reorder' => 12, 'months' => 26],
            ['name' => 'Vitamin D3 50000IU', 'category' => 'Vitamins', 'manufacturer' => 'Afamia Pharma', 'cost' => 12000, 'retail' => 18500, 'reorder' => 10, 'months' => 22],
            ['name' => 'Prenatal Multivitamin', 'category' => 'Vitamins', 'manufacturer' => 'Zenobia Labs', 'cost' => 14000, 'retail' => 21000, 'reorder' => 12, 'months' => 20, 'stockists' => [2, 3]],

            // ---- Antidiabetics ----------------------------------------------
            ['name' => 'Metformin 850mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Palmyra Labs', 'cost' => 7500, 'retail' => 12000, 'reorder' => 25, 'months' => 22],
            ['name' => 'Gliclazide 80mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Qasioun Labs', 'cost' => 8500, 'retail' => 13500, 'reorder' => 20, 'months' => 20],
            ['name' => 'Metformin 500mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Orontes Labs', 'cost' => 6000, 'retail' => 10000, 'reorder' => 30, 'months' => 24],
            ['name' => 'Glimepiride 2mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Ugarit Pharma', 'cost' => 9000, 'retail' => 14000, 'reorder' => 20, 'months' => 23],
            ['name' => 'Glibenclamide 5mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Afamia Pharma', 'cost' => 5500, 'retail' => 9000, 'reorder' => 20, 'months' => 21],
            ['name' => 'Sitagliptin 100mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Zenobia Labs', 'cost' => 26000, 'retail' => 37000, 'reorder' => 8, 'months' => 19, 'stockists' => [1, 2]],
            ['name' => 'Pioglitazone 30mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Qasioun Labs', 'cost' => 11000, 'retail' => 17000, 'reorder' => 12, 'months' => 22],
            ['name' => 'Empagliflozin 10mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Orontes Labs', 'cost' => 29000, 'retail' => 41000, 'reorder' => 8, 'months' => 18, 'stockists' => [1, 2]],
            ['name' => 'Metformin XR 1000mg', 'category' => 'Antidiabetics', 'manufacturer' => 'Ugarit Pharma', 'cost' => 9500, 'retail' => 15000, 'reorder' => 20, 'months' => 24],
            ['name' => 'Insulin Glargine Pen', 'category' => 'Antidiabetics', 'manufacturer' => 'Mari Pharma', 'cost' => 75000, 'retail' => 105000, 'reorder' => 6, 'months' => 12, 'stockists' => [3]],
            ['name' => 'Insulin Regular Vial', 'category' => 'Antidiabetics', 'manufacturer' => 'Bosra Labs', 'cost' => 45000, 'retail' => 65000, 'reorder' => 6, 'months' => 12, 'stockists' => [3]],
            ['name' => 'Insulin NPH Vial', 'category' => 'Antidiabetics', 'manufacturer' => 'Palmyra Labs', 'cost' => 42000, 'retail' => 60000, 'reorder' => 6, 'months' => 12, 'stockists' => [3]],

            // ---- Gastrointestinal -------------------------------------------
            ['name' => 'Omeprazole 20mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Qasioun Labs', 'cost' => 6500, 'retail' => 11000, 'reorder' => 25, 'months' => 23],
            ['name' => 'Domperidone 10mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Orontes Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 20, 'months' => 17],
            ['name' => 'Metoclopramide 10mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Ugarit Pharma', 'cost' => 4500, 'retail' => 7500, 'reorder' => 15, 'months' => 16],
            ['name' => 'Esomeprazole 40mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Palmyra Labs', 'cost' => 14000, 'retail' => 21000, 'reorder' => 15, 'months' => 22],
            ['name' => 'Pantoprazole 40mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Afamia Pharma', 'cost' => 10000, 'retail' => 15500, 'reorder' => 18, 'months' => 24],
            ['name' => 'Ranitidine 150mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Zenobia Labs', 'cost' => 5500, 'retail' => 9000, 'reorder' => 20, 'months' => 20],
            ['name' => 'Loperamide 2mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Mari Pharma', 'cost' => 4000, 'retail' => 7000, 'reorder' => 25, 'months' => 26],
            ['name' => 'Lactulose Syrup', 'category' => 'Gastrointestinal', 'manufacturer' => 'Bosra Labs', 'cost' => 8000, 'retail' => 13000, 'reorder' => 15, 'months' => 18],
            ['name' => 'Simethicone 40mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Qasioun Labs', 'cost' => 4500, 'retail' => 7500, 'reorder' => 20, 'months' => 25],
            ['name' => 'Mebeverine 135mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Orontes Labs', 'cost' => 9000, 'retail' => 14000, 'reorder' => 15, 'months' => 21],
            ['name' => 'Bisacodyl 5mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Ugarit Pharma', 'cost' => 3500, 'retail' => 6000, 'reorder' => 20, 'months' => 27],
            ['name' => 'Oral Rehydration Salts', 'category' => 'Gastrointestinal', 'manufacturer' => 'Palmyra Labs', 'cost' => 2500, 'retail' => 4500, 'reorder' => 40, 'months' => 30],
            ['name' => 'Hyoscine 10mg', 'category' => 'Gastrointestinal', 'manufacturer' => 'Afamia Pharma', 'cost' => 6000, 'retail' => 10000, 'reorder' => 18, 'months' => 23],
            ['name' => 'Antacid Suspension', 'category' => 'Gastrointestinal', 'manufacturer' => 'Zenobia Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 25, 'months' => 19],

            // ---- Respiratory -------------------------------------------------
            ['name' => 'Cetirizine 10mg', 'category' => 'Respiratory', 'manufacturer' => 'Orontes Labs', 'cost' => 4000, 'retail' => 7500, 'reorder' => 10, 'months' => 20],
            ['name' => 'Loratadine 10mg', 'category' => 'Respiratory', 'manufacturer' => 'Ugarit Pharma', 'cost' => 5000, 'retail' => 8000, 'reorder' => 15, 'months' => 25],
            ['name' => 'Salbutamol Inhaler', 'category' => 'Respiratory', 'manufacturer' => 'Palmyra Labs', 'cost' => 18000, 'retail' => 26000, 'reorder' => 10, 'months' => 14],
            ['name' => 'Montelukast 10mg', 'category' => 'Respiratory', 'manufacturer' => 'Qasioun Labs', 'cost' => 15000, 'retail' => 22000, 'reorder' => 12, 'months' => 22],
            ['name' => 'Ambroxol Syrup', 'category' => 'Respiratory', 'manufacturer' => 'Zenobia Labs', 'cost' => 5500, 'retail' => 9000, 'reorder' => 20, 'months' => 18],
            ['name' => 'Acetylcysteine 600mg', 'category' => 'Respiratory', 'manufacturer' => 'Mari Pharma', 'cost' => 9000, 'retail' => 14000, 'reorder' => 15, 'months' => 20],
            ['name' => 'Dextromethorphan Syrup', 'category' => 'Respiratory', 'manufacturer' => 'Bosra Labs', 'cost' => 6000, 'retail' => 10000, 'reorder' => 20, 'months' => 17],
            ['name' => 'Fexofenadine 120mg', 'category' => 'Respiratory', 'manufacturer' => 'Qasioun Labs', 'cost' => 11000, 'retail' => 16500, 'reorder' => 12, 'months' => 24],
            ['name' => 'Chlorpheniramine 4mg', 'category' => 'Respiratory', 'manufacturer' => 'Zenobia Labs', 'cost' => 3000, 'retail' => 5500, 'reorder' => 25, 'months' => 26],
            ['name' => 'Salbutamol Syrup', 'category' => 'Respiratory', 'manufacturer' => 'Palmyra Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 18, 'months' => 15],
            ['name' => 'Theophylline 200mg', 'category' => 'Respiratory', 'manufacturer' => 'Ugarit Pharma', 'cost' => 6500, 'retail' => 10500, 'reorder' => 12, 'months' => 23, 'stockists' => [2]],
            ['name' => 'Budesonide Inhaler', 'category' => 'Respiratory', 'manufacturer' => 'Afamia Pharma', 'cost' => 24000, 'retail' => 34000, 'reorder' => 8, 'months' => 15, 'stockists' => [1, 3]],
            ['name' => 'Beclometasone Inhaler', 'category' => 'Respiratory', 'manufacturer' => 'Orontes Labs', 'cost' => 20000, 'retail' => 29000, 'reorder' => 8, 'months' => 16],
            ['name' => 'Ipratropium Inhaler', 'category' => 'Respiratory', 'manufacturer' => 'Afamia Pharma', 'cost' => 22000, 'retail' => 31000, 'reorder' => 8, 'months' => 16, 'stockists' => [1, 3]],

            // ---- Cardiovascular ----------------------------------------------
            ['name' => 'Amlodipine 5mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Qasioun Labs', 'cost' => 7000, 'retail' => 11000, 'reorder' => 20, 'months' => 29],
            ['name' => 'Atorvastatin 20mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Orontes Labs', 'cost' => 13000, 'retail' => 19000, 'reorder' => 15, 'months' => 26],
            ['name' => 'Bisoprolol 5mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Ugarit Pharma', 'cost' => 9000, 'retail' => 14000, 'reorder' => 20, 'months' => 23],
            ['name' => 'Lisinopril 10mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Palmyra Labs', 'cost' => 8000, 'retail' => 12500, 'reorder' => 20, 'months' => 25],
            ['name' => 'Losartan 50mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Afamia Pharma', 'cost' => 10000, 'retail' => 15500, 'reorder' => 20, 'months' => 24],
            ['name' => 'Valsartan 80mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Zenobia Labs', 'cost' => 12000, 'retail' => 18000, 'reorder' => 15, 'months' => 22],
            ['name' => 'Enalapril 10mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Mari Pharma', 'cost' => 6500, 'retail' => 10500, 'reorder' => 20, 'months' => 27],
            ['name' => 'Furosemide 40mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Bosra Labs', 'cost' => 4000, 'retail' => 7000, 'reorder' => 25, 'months' => 28],
            ['name' => 'Hydrochlorothiazide 25mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Qasioun Labs', 'cost' => 3500, 'retail' => 6000, 'reorder' => 25, 'months' => 30],
            ['name' => 'Rosuvastatin 10mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Orontes Labs', 'cost' => 16000, 'retail' => 23000, 'reorder' => 12, 'months' => 24],
            ['name' => 'Simvastatin 20mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Ugarit Pharma', 'cost' => 9500, 'retail' => 14500, 'reorder' => 15, 'months' => 26],
            ['name' => 'Clopidogrel 75mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Palmyra Labs', 'cost' => 18000, 'retail' => 26000, 'reorder' => 12, 'months' => 21],
            ['name' => 'Nitroglycerin 0.5mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Zenobia Labs', 'cost' => 8500, 'retail' => 13000, 'reorder' => 10, 'months' => 18],
            ['name' => 'Warfarin 5mg', 'category' => 'Cardiovascular', 'manufacturer' => 'Afamia Pharma', 'cost' => 7500, 'retail' => 12000, 'reorder' => 10, 'months' => 25, 'stockists' => [1]],

            // ---- Dermatology --------------------------------------------------
            ['name' => 'Betamethasone Cream', 'category' => 'Dermatology', 'manufacturer' => 'Palmyra Labs', 'cost' => 6000, 'retail' => 9500, 'reorder' => 12, 'months' => 18],
            ['name' => 'Clotrimazole Cream', 'category' => 'Dermatology', 'manufacturer' => 'Qasioun Labs', 'cost' => 5000, 'retail' => 8500, 'reorder' => 15, 'months' => 21],
            ['name' => 'Hydrocortisone 1% Cream', 'category' => 'Dermatology', 'manufacturer' => 'Orontes Labs', 'cost' => 4500, 'retail' => 7500, 'reorder' => 15, 'months' => 20],
            ['name' => 'Mupirocin Ointment', 'category' => 'Dermatology', 'manufacturer' => 'Ugarit Pharma', 'cost' => 11000, 'retail' => 16500, 'reorder' => 10, 'months' => 19],
            ['name' => 'Fusidic Acid Cream', 'category' => 'Dermatology', 'manufacturer' => 'Afamia Pharma', 'cost' => 9500, 'retail' => 15000, 'reorder' => 12, 'months' => 22],
            ['name' => 'Ketoconazole Shampoo', 'category' => 'Dermatology', 'manufacturer' => 'Zenobia Labs', 'cost' => 13000, 'retail' => 19500, 'reorder' => 10, 'months' => 24],
            ['name' => 'Terbinafine Cream', 'category' => 'Dermatology', 'manufacturer' => 'Mari Pharma', 'cost' => 8500, 'retail' => 13500, 'reorder' => 12, 'months' => 23],
            ['name' => 'Benzoyl Peroxide 5% Gel', 'category' => 'Dermatology', 'manufacturer' => 'Bosra Labs', 'cost' => 7000, 'retail' => 11500, 'reorder' => 12, 'months' => 18],
            ['name' => 'Calamine Lotion', 'category' => 'Dermatology', 'manufacturer' => 'Qasioun Labs', 'cost' => 3500, 'retail' => 6500, 'reorder' => 20, 'months' => 26],
            ['name' => 'Permethrin 5% Cream', 'category' => 'Dermatology', 'manufacturer' => 'Orontes Labs', 'cost' => 10000, 'retail' => 15500, 'reorder' => 10, 'months' => 20, 'stockists' => [2]],
            ['name' => 'Adapalene 0.1% Gel', 'category' => 'Dermatology', 'manufacturer' => 'Palmyra Labs', 'cost' => 14000, 'retail' => 20500, 'reorder' => 10, 'months' => 19, 'stockists' => [2, 3]],
            ['name' => 'Silver Sulfadiazine Cream', 'category' => 'Dermatology', 'manufacturer' => 'Ugarit Pharma', 'cost' => 12000, 'retail' => 18000, 'reorder' => 8, 'months' => 17, 'stockists' => [3]],
        ];
    }
}
