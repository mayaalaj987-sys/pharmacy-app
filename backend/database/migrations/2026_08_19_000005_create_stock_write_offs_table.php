<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock that left the shelf without being sold.
 *
 * Until now inventory could only go up, by receiving, or down, by selling.
 * Everything else that actually happens to stock — it expires, it breaks, it
 * walks out the door — had nowhere to go except a pharmacist quietly editing
 * the quantity, which leaves no record of what happened or what it cost.
 *
 * The consequence was worse than untidy. Stock bought and never sold never
 * entered cost of goods, so its cost vanished from the books entirely: it had
 * not reduced profit when it was bought, it never reduced profit when it was
 * written off, and it sat in the inventory valuation as an asset forever. A
 * pharmacy could lose real money and see it nowhere.
 *
 * Append-only. A write-off is a record of something that happened, and the
 * quantity it removed cannot be un-removed by editing the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();

            // The batch it came off. Kept even if the row is later emptied, so
            // the loss stays attached to the stock that caused it.
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();

            // Copied rather than joined: the drug's name and cost both move,
            // and a loss recorded last March must still read as it did then.
            $table->string('medicine_name');
            $table->decimal('unit_cost', 10, 2);
            $table->integer('quantity');

            $table->string('reason', 24);
            $table->string('note')->nullable();

            // Who signed it off. Nullable because staff records can be removed
            // and losing the whole loss with them would be worse.
            $table->foreignId('pharmacist_id')->nullable()->constrained('pharmacists')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->timestamps();

            // Every report over this reads a pharmacy and a date range.
            $table->index(['pharmacy_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_write_offs');
    }
};
