<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a notification be addressed to a person, not only to a pharmacy.
 *
 * `pharmacy_id` was NOT NULL, which is the single reason nothing in this
 * system could ever tell an employee anything. Someone waiting on a job has no
 * pharmacy at all, so every message meant for them — an offer arrived, a
 * pharmacy read your CV — had nowhere to go.
 *
 * Exactly one of the two columns is set, the shape `support_tickets` already
 * uses for a record whose sender may be either kind of user. A second
 * `employee_notifications` table was the alternative and would have meant two
 * bells, two policies and two ideas of what "unread" means.
 *
 * This is safe against leaking across the boundary by construction rather than
 * by a new guard: every existing read filters `where('pharmacy_id', ...)`, and
 * an employee-addressed row has a null pharmacy_id, so it can never appear in
 * a pharmacy's list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('pharmacy_id')->nullable()->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('pharmacy_id')
                ->constrained('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'is_read'], 'notifications_employee_read_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_employee_read_index');
            $table->dropConstrainedForeignId('employee_id');
        });

        // Anything addressed to a person has no pharmacy to fall back on.
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('pharmacy_id')->nullable(false)->change();
        });
    }
};
