<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedInteger('review_version')->default(0);
        });

        foreach (['pharmacy_document_versions', 'employee_document_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->uuid('public_id')->nullable();
                $table->unique('public_id', $tableName.'_public_id_unique');
            });

            DB::table($tableName)->orderBy('id')->eachById(function (object $row) use ($tableName): void {
                DB::table($tableName)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
            });
        }
    }

    public function down(): void
    {
        foreach (['pharmacy_document_versions', 'employee_document_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_public_id_unique');
                $table->dropColumn('public_id');
            });
        }

        Schema::table('pharmacies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_admin_id');
            $table->dropColumn(['reviewed_at', 'rejection_reason', 'review_version']);
        });
    }
};
