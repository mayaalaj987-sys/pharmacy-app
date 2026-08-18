<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every job anyone has held, including the ones that ended.
 *
 * Nothing recorded that a job had happened. Someone who worked three years and
 * left went back to `status = pending` — indistinguishable in the data from a
 * graduate who registered this morning. There was no start date, no end date
 * and no record of who ended it.
 *
 * This is an append-only log, deliberately *not* the source of truth for who
 * works where: `employees.pharmacy_id` and `employees.shift` keep that job,
 * with the unique index that enforces one person per shift. Fifty-odd places
 * read those columns and none of them move. This table only answers "what
 * happened", which is the question ratings and history need and the one
 * nothing could answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();

            $table->string('shift', 16);
            $table->decimal('salary', 10, 2)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            // Who ended it: 'employee' resigned, 'pharmacy' dismissed. Null
            // while the job is still running. The notifications said which at
            // the time and then the fact was gone.
            $table->string('ended_by', 16)->nullable();

            $table->timestamps();

            // "Their history" and "who worked here" are the only two reads.
            $table->index(['employee_id', 'ended_at'], 'employments_employee_index');
            $table->index(['pharmacy_id', 'ended_at'], 'employments_pharmacy_index');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('employments');
    }

    /**
     * Open an employment for everyone currently working somewhere.
     *
     * The accepted offer carries the terms and the date it was answered, which
     * is the closest thing to a start date that was ever recorded. Jobs that
     * ended before this migration are simply lost — nothing stored them, so
     * there is nothing to recover.
     */
    private function backfill(): void
    {
        $now = now();

        $employed = DB::table('employees')
            ->leftJoin('job_offers', function ($join) {
                $join->on('job_offers.employee_id', '=', 'employees.id')
                    ->on('job_offers.pharmacy_id', '=', 'employees.pharmacy_id');
            })
            ->whereNotNull('employees.pharmacy_id')
            ->whereNotNull('employees.shift')
            ->get([
                'employees.id as employee_id',
                'employees.pharmacy_id',
                'employees.shift',
                'employees.salary',
                'employees.created_at',
                'job_offers.responded_at',
            ]);

        if ($employed->isEmpty()) {
            return;
        }

        DB::table('employments')->insert(
            $employed->map(fn ($row) => [
                'employee_id' => $row->employee_id,
                'pharmacy_id' => $row->pharmacy_id,
                'shift' => $row->shift,
                'salary' => $row->salary,
                'started_at' => $row->responded_at ?? $row->created_at ?? $now,
                'ended_at' => null,
                'ended_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }
};
