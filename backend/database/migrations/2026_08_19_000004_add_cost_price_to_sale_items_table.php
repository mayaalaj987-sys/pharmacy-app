<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the goods on this line actually cost the pharmacy.
 *
 * Profit is revenue minus cost of goods, and the reports worked that out by
 * joining back to `medicines` and reading `cost_price` as it stands today. That
 * was defensible only while a medicine's cost never changed. It changes now —
 * receiving blends a new delivery into the recorded cost — so an old sale
 * silently reprices itself every time fresh stock arrives, and last year's
 * profit is not the same number it was last year.
 *
 * Frozen here at the moment of sale, alongside the selling price that was
 * already frozen on the same row. A sale is a financial record; nothing about
 * it should move afterwards.
 *
 * Backfilled from the current cost, which is the best available answer for
 * sales that happened before this column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->default(0)->after('price');
        });

        // Nullable would force every reader to handle "unknown cost", which is
        // not a state anything can act on. The current cost is the honest
        // estimate for rows that predate the column.
        DB::statement(
            'UPDATE sale_items SET cost_price = COALESCE((
                SELECT medicines.cost_price FROM medicines WHERE medicines.id = sale_items.medicine_id
            ), 0)'
        );
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
