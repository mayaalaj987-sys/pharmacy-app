<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pharmacy asking a specific person to cover a specific shift.
 *
 * Hiring used to be one call: any pharmacist could attach any applicant to
 * their pharmacy outright, and the applicant found out by logging in. An offer
 * is the same act split in two, with the applicant's answer in the middle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            // Both sides cascade: unlike a support ticket, whose text outlives
            // its author, an offer means nothing without either party.
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('created_by_pharmacist_id')->nullable()
                ->constrained('pharmacists')->nullOnDelete();

            $table->string('shift', 16);
            $table->decimal('salary', 10, 2)->nullable();

            // Not an enum: `declined` and `expired` are obvious later states and
            // altering an enum is a migration nobody wants. Mirrors
            // employee_document_versions.review_status.
            $table->string('status', 16)->default('pending');

            // Distinct from created_at, which a re-offer must not disturb: an
            // offer reopened today should sort as today's, not as March's.
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // One live offer per pharmacy per person. Re-offering updates the
            // row, so a pharmacy cannot spam an applicant by sending again, and
            // changing their mind about the shift is an edit rather than a
            // second competing offer.
            $table->unique(['pharmacy_id', 'employee_id'], 'job_offers_pharmacy_employee_unique');
            $table->index(['employee_id', 'status'], 'job_offers_employee_status_index');
            $table->index(['pharmacy_id', 'status'], 'job_offers_pharmacy_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
