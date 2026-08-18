<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a pharmacy's notification is actually for.
 *
 * Every pharmacy-scoped notification went to everyone at that pharmacy, so an
 * employee's bell filled with the owner's business — supplier orders, stock
 * warnings, the takings from their own sales — while the things genuinely
 * addressed to them were buried in it.
 *
 * Two audiences is enough, and both are needed:
 *
 *  - `owner`  the pharmacist's business. Money, stock, suppliers, staffing.
 *  - `staff`  anything the people working the counter should see.
 *
 * Anything meant for one named person does not use this at all: it is
 * addressed with `employee_id` and reaches nobody else.
 *
 * Defaulting to `owner` is the safe direction — a new notification that nobody
 * classified stays with the person who owns the shop rather than leaking to
 * staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('audience', 16)->default('owner')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
