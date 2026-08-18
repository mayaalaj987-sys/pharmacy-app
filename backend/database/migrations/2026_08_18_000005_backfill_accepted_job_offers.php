<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give everyone already employed the offer they would have accepted.
 *
 * Without this, a pharmacy that hired its staff before offers existed opens
 * the new screen to nothing, and every all-time placement figure reads zero.
 * The offer is recorded as accepted at the moment the employee row was
 * created, which is the closest thing to a hiring date this schema kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $employed = DB::table('employees')
            ->join('pharmacies', 'pharmacies.id', '=', 'employees.pharmacy_id')
            ->whereNotNull('employees.pharmacy_id')
            ->whereNotNull('employees.shift')
            ->get([
                'employees.id as employee_id',
                'employees.pharmacy_id',
                'employees.shift',
                'employees.salary',
                'employees.created_at',
                'pharmacies.pharmacist_id',
            ]);

        if ($employed->isEmpty()) {
            return;
        }

        DB::table('job_offers')->insertOrIgnore(
            $employed->map(fn ($row) => [
                'pharmacy_id' => $row->pharmacy_id,
                'employee_id' => $row->employee_id,
                'created_by_pharmacist_id' => $row->pharmacist_id,
                'shift' => $row->shift,
                'salary' => $row->salary,
                'status' => 'accepted',
                'offered_at' => $row->created_at ?? $now,
                'responded_at' => $row->created_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        DB::table('job_offers')->where('status', 'accepted')->delete();
    }
};
