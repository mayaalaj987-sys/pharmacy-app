<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 32);
            $table->unsignedInteger('version_number');
            $table->string('storage_key', 512)->unique();
            $table->string('verified_mime_type', 64);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('review_status', 24)->default('pending');
            $table->nullableMorphs('uploaded_by');
            $table->nullableMorphs('reviewed_by');
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->char('legacy_locator_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'document_type', 'version_number'], 'pharmacy_document_version_unique');
            $table->index(['pharmacy_id', 'document_type', 'review_status'], 'pharmacy_document_review_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_document_versions');
    }
};
