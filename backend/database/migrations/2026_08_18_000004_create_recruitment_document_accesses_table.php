<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who opened whose CV, and when.
 *
 * Recruiters can read any applicant's documents, which is the only way to
 * decide whether to make an offer. There is deliberately no quota on that —
 * the control is attribution rather than scarcity. This table is what makes
 * the attribution real, and the applicant is notified from it, so it has a
 * reader rather than being a log nobody ever queries.
 *
 * Modelled on admin_audit_logs but without its append-only triggers: that
 * machinery exists because administrator actions are contestable, and reading
 * a CV is not a decision anyone will need to litigate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_document_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacist_id')->constrained('pharmacists')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();
            // The subject of the record: it must outlive any tidying up, so the
            // employee and the document version cannot be deleted out from under
            // their own access history.
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employee_document_version_id')
                ->constrained('employee_document_versions')->restrictOnDelete();

            $table->string('action', 16); // previewed | downloaded
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // One time matters, so no created_at/updated_at pair to keep in sync.
            $table->timestamp('accessed_at');

            $table->index(['employee_id', 'accessed_at'], 'recruitment_access_employee_index');
            $table->index(['pharmacist_id', 'accessed_at'], 'recruitment_access_pharmacist_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_document_accesses');
    }
};
