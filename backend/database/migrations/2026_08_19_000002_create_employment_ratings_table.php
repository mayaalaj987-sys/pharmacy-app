<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each side thought of the other, once the job is over.
 *
 * Attached to an employment rather than to a pharmacy or a person, which is
 * what makes it trustworthy: you can only rate a job you actually held, the
 * period being judged is known, and neither side can rate a stranger.
 *
 * Not to be confused with `ratings`, which is a pharmacist rating the
 * application itself and has nothing to do with anybody's work.
 *
 * Stars only, no free text. Two people work at a pharmacy; a written complaint
 * would identify its author however the author is labelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_ratings', function (Blueprint $table) {
            $table->id();
            // Restricted rather than cascading: the rating is evidence about a
            // job, and the job must not be removable out from under it.
            $table->foreignId('employment_id')->constrained('employments')->restrictOnDelete();

            // Which way it points. Both sides rate the same employment, so the
            // direction is what separates their two rows.
            $table->string('direction', 24);

            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            // One verdict per side per job. Rating again edits it rather than
            // stacking, so nobody can weight an average by repeating themselves.
            $table->unique(['employment_id', 'direction'], 'employment_rating_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_ratings');
    }
};
