<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer bringing something back.
 *
 * It happens daily in a pharmacy and there was no way to record it: a sale is
 * written once and never touched again. That is the right instinct — a sale is
 * a financial record, and editing one to make a refund fit destroys the
 * evidence of what was actually sold — so a return is an entry of its own that
 * points back at the line it reverses.
 *
 * The refund is stored rather than recomputed. The shelf price moves, and a
 * customer is owed what they paid, not what the box costs today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();

            // The exact line being reversed, which is also the batch the boxes
            // came off — so returned stock goes back where it belongs and keeps
            // its own expiry date rather than joining an unrelated pile.
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();

            $table->integer('quantity');
            $table->decimal('refund_amount', 10, 2);
            $table->string('reason', 32);
            $table->string('note')->nullable();

            $table->foreignId('pharmacist_id')->nullable()->constrained('pharmacists')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->timestamps();

            // Reports read a pharmacy over a date range; the refund check for a
            // given line reads by sale item.
            $table->index(['pharmacy_id', 'created_at']);
            $table->index('sale_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
