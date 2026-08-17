<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an administrator suspend a pharmacy without rewriting its review status.
 *
 * Suspension is deliberately a separate axis rather than a fourth `status`
 * value. `status` records the outcome of the review — it is read in eight
 * places and a new enum member would have to be audited in all of them — while
 * a suspension is a temporary operational block that can be lifted, leaving the
 * original "approved" decision intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('reviewed_at');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
            // Restricted, like the audit log: a suspension must keep naming
            // the administrator who imposed it.
            $table->foreignId('blocked_by_admin_id')->nullable()->after('blocked_reason')
                ->constrained('admins')->restrictOnDelete();

            $table->index(['blocked_at'], 'pharmacies_blocked_index');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropIndex('pharmacies_blocked_index');
            $table->dropConstrainedForeignId('blocked_by_admin_id');
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
