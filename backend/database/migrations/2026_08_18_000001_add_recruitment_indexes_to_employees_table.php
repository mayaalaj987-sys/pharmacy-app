<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the two queries recruitment leans on.
 *
 * The hiring pool scans for unattached applicants and every roster read filters
 * a pharmacy's staff by status; neither had an index, so both were full table
 * scans. `2026_08_17_000001_add_performance_indexes` covered eight tables and
 * skipped this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasIndex('employees', 'employees_pharmacy_id_status_index')) {
                $table->index(['pharmacy_id', 'status'], 'employees_pharmacy_id_status_index');
            }
            if (! Schema::hasIndex('employees', 'employees_status_index')) {
                $table->index('status', 'employees_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasIndex('employees', 'employees_pharmacy_id_status_index')) {
                $table->dropIndex('employees_pharmacy_id_status_index');
            }
            if (Schema::hasIndex('employees', 'employees_status_index')) {
                $table->dropIndex('employees_status_index');
            }
        });
    }
};
