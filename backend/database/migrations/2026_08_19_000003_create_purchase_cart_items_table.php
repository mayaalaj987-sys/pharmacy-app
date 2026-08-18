<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a pharmacy intends to buy, before it has bought it.
 *
 * One cart per pharmacy, holding offers from as many suppliers as it likes;
 * checkout is what splits it into an order per supplier. Nothing here is a
 * commitment — no supplier stock is reserved and no money is owed until
 * checkout, which is precisely what lets the app add a line on its own and
 * leave the decision to the pharmacist. The cart *is* the approval step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();

            // A supplier's catalogue row, not a pharmacy stock row. Which
            // supplier is being bought from is carried by this medicine, so the
            // cart never has to store it separately and the two cannot disagree.
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();

            $table->integer('quantity');

            // Whether the pharmacist put this here or the app did. Drives how
            // the line is presented: something the app suggested has to be
            // recognisable as a suggestion, or the pharmacist ends up buying
            // stock they never chose.
            $table->string('added_by', 16)->default('pharmacist');

            $table->timestamps();

            // One line per offer. Adding the same offer twice raises the
            // quantity instead of splitting it across two rows, so the cart can
            // never show one drug on two lines at the same price.
            $table->unique(['pharmacy_id', 'medicine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_cart_items');
    }
};
