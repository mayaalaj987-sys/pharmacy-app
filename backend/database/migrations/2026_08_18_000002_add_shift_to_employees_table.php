<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A pharmacy's staff work opposite shifts, so a shift is a seat.
 *
 * `unique(pharmacy_id, shift)` turns headcount into something the database
 * enforces. It replaces a counted cap that could not be made safe: the old
 * check locked the employee row and then counted the pharmacy, so two
 * concurrent hires each counted one and both committed — and on SQLite, which
 * this app runs on, `lockForUpdate()` does nothing at all.
 *
 * Unattached applicants are exempt for free: they have a null `pharmacy_id`
 * and a null `shift`, and every SQL engine treats NULLs as distinct in a
 * unique index, so the whole hiring pool can sit in this table unconstrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('shift', 16)->nullable()->after('role');
        });

        $this->backfill();

        Schema::table('employees', function (Blueprint $table) {
            $table->unique(['pharmacy_id', 'shift'], 'employees_pharmacy_shift_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_pharmacy_shift_unique');
            $table->dropColumn('shift');
        });
    }

    /**
     * Give every already-employed person a shift, oldest first.
     *
     * The assignment is arbitrary — nothing recorded who opened and who closed
     * — but it is deterministic, and a pharmacist can correct it by moving
     * someone. What matters is that no two people at one pharmacy end up
     * sharing a shift, because the unique index is added immediately after.
     */
    private function backfill(): void
    {
        $shifts = Employee::SHIFTS;

        $employed = DB::table('employees')
            ->whereNotNull('pharmacy_id')
            ->orderBy('pharmacy_id')
            ->orderBy('id')
            ->get(['id', 'pharmacy_id']);

        $overfull = $employed->groupBy('pharmacy_id')
            ->filter(fn ($rows) => $rows->count() > count($shifts))
            ->keys();

        if ($overfull->isNotEmpty()) {
            // Refuse rather than pick winners. Assigning shifts to only the
            // first two and detaching the rest would quietly fire someone.
            throw new RuntimeException(
                'Cannot add the shift constraint: pharmacies '.$overfull->implode(', ').
                ' already hold more than '.count($shifts).' employees. '.
                'Detach the extras first, then re-run this migration.'
            );
        }

        foreach ($employed->groupBy('pharmacy_id') as $rows) {
            foreach ($rows->values() as $index => $row) {
                DB::table('employees')
                    ->where('id', $row->id)
                    ->update(['shift' => $shifts[$index]]);
            }
        }
    }
};
