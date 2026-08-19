<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the pharmacist wanted to say about the app.
 *
 * A star on its own records that somebody was unhappy without recording why,
 * which makes the whole rating unactionable — the one thing feedback has to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->string('note', 1000)->nullable()->after('stars');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
