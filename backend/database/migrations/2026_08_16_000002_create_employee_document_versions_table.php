<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 32);
            $table->unsignedInteger('version_number');
            $table->string('storage_key', 512)->unique();
            $table->string('verified_mime_type', 64);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('review_status', 24)->nullable();
            $table->nullableMorphs('uploaded_by');
            $table->nullableMorphs('reviewed_by');
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->char('legacy_locator_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['employee_id', 'document_type', 'version_number'], 'employee_document_version_unique');
            $table->index(['employee_id', 'document_type'], 'employee_document_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_versions');
    }
};
