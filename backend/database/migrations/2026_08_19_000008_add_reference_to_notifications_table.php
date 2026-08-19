<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the notification is about, so tapping it can go there.
 *
 * Every notification in this app was a dead end: it told the pharmacist an
 * order had arrived or a drug had run out, and tapping it marked it read. They
 * then had to go and find the thing themselves, which makes each notification a
 * small chore rather than a shortcut.
 *
 * The type already says what kind of thing it concerns; this says which one.
 * Nullable because plenty of them refer to nothing in particular — an
 * announcement, a pharmacy approval — and inventing an id for those would be
 * worse than admitting there isn't one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('reference_id');
        });
    }
};
