<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * A pharmacy's staff work opposite shifts, so a shift is a seat.
 *
 * This replaces a counted cap that could never have held: the old check locked
 * the employee row and then counted the pharmacy, so two concurrent hires each
 * counted one and both committed — and this app runs on SQLite, where
 * `lockForUpdate()` does nothing whatsoever. The guarantee now lives in a
 * unique index, and the last test here proves it by writing straight past the
 * application code.
 */
class ShiftCapacityTest extends SecurityTestCase
{
    public function test_a_pharmacy_covers_one_morning_and_one_evening(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-both');
        $first = $this->applicant('cap-both-1');
        $second = $this->applicant('cap-both-2');

        $this->hire($owner, $pharmacy, $first, Employee::SHIFT_MORNING, 500000)->assertOk();
        $this->hire($owner, $pharmacy, $second, Employee::SHIFT_EVENING)->assertOk();

        $this->assertSame('morning', $first->fresh()->shift);
        $this->assertSame('evening', $second->fresh()->shift);

        $this->assertSame([], $pharmacy->fresh()->freeShifts());
    }

    public function test_a_second_person_cannot_take_a_covered_shift(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-clash');
        $held = $this->applicant('cap-clash-1');
        $rejected = $this->applicant('cap-clash-2');

        $this->hire($owner, $pharmacy, $held, Employee::SHIFT_MORNING)->assertOk();

        // Refused at the offer step now, so an applicant is never shown terms
        // the pharmacy could not honour. The refusal still names the shift that
        // is open, so the pharmacist can act instead of guessing.
        $this->hire($owner, $pharmacy, $rejected, Employee::SHIFT_MORNING)
            ->assertStatus(409)
            ->assertJsonPath('code', 'shift_taken')
            ->assertJsonPath('free_shifts', ['evening']);

        $this->assertNull($rejected->fresh()->pharmacy_id);
    }

    public function test_a_third_hire_is_refused_once_every_shift_is_covered(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-third');
        foreach (['cap-third-1', 'cap-third-2'] as $suffix) {
            $this->hire($owner, $pharmacy, $this->applicant($suffix))->assertOk();
        }

        $third = $this->applicant('cap-third-3');

        $this->hire($owner, $pharmacy, $third)
            ->assertStatus(409)
            ->assertJsonPath('code', 'shift_taken')
            ->assertJsonPath('free_shifts', []);

        $this->assertSame(2, Employee::where('pharmacy_id', $pharmacy->id)->count());
    }

    public function test_hiring_fills_the_free_shifts_in_order(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-implicit');
        $first = $this->applicant('cap-implicit-1');
        $second = $this->applicant('cap-implicit-2');

        $this->hire($owner, $pharmacy, $first)->assertOk();
        $this->hire($owner, $pharmacy, $second)->assertOk();

        $this->assertSame('morning', $first->fresh()->shift);
        $this->assertSame('evening', $second->fresh()->shift);
    }

    public function test_nobody_can_be_hired_without_their_own_consent(): void
    {
        // The route that let one pharmacist attach a person outright is gone.
        // That is the whole change, so it is asserted rather than assumed.
        [$owner, $pharmacy] = $this->hiringOwner('cap-noconsent');
        $applicant = $this->applicant('cap-noconsent-1');

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$applicant->id, ['pharmacy_id' => $pharmacy->id])
            ->assertNotFound();

        $this->assertFalse($applicant->fresh()->isEmployed());
    }

    public function test_a_trainee_may_be_paid(): void
    {
        // The salary a pharmacist typed for a trainee used to be discarded in
        // silence. Whether training is paid is the pharmacy's decision.
        [$owner, $pharmacy] = $this->hiringOwner('cap-trainee');
        $trainee = $this->applicant('cap-trainee-1', Employee::ROLE_TRAINEE);

        $this->hire($owner, $pharmacy, $trainee, null, 150000)->assertOk();

        $this->assertSame(150000.0, (float) $trainee->fresh()->salary);
    }

    public function test_two_pharmacies_may_each_cover_the_same_shift(): void
    {
        // The constraint is per pharmacy, not global.
        [$firstOwner, $firstPharmacy] = $this->hiringOwner('cap-a');
        [$secondOwner, $secondPharmacy] = $this->hiringOwner('cap-b');

        foreach ([[$firstOwner, $firstPharmacy, 'cap-a-1'], [$secondOwner, $secondPharmacy, 'cap-b-1']] as [$owner, $pharmacy, $suffix]) {
            $this->hire($owner, $pharmacy, $this->applicant($suffix), Employee::SHIFT_MORNING)
                ->assertOk();
        }

        $this->assertSame(2, Employee::where('shift', Employee::SHIFT_MORNING)->count());
    }

    public function test_the_database_refuses_a_double_booking_even_without_the_service_check(): void
    {
        // The point of moving capacity into a unique index. Application checks
        // are advisory on a driver with no row locking; this write bypasses
        // every one of them and must still fail.
        [, $pharmacy] = $this->hiringOwner('cap-index');
        $held = $this->applicant('cap-index-1');
        $held->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
            'status' => Employee::STATUS_APPROVED,
        ])->save();

        $intruder = $this->applicant('cap-index-2');

        $this->expectException(QueryException::class);

        $intruder->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
            'status' => Employee::STATUS_APPROVED,
        ])->save();
    }

    public function test_the_pool_is_exempt_from_the_constraint(): void
    {
        // Every applicant has a null pharmacy and a null shift. SQL treats
        // NULLs as distinct in a unique index, so the pool is unbounded without
        // needing a partial index the driver may not support.
        foreach (range(1, 5) as $index) {
            $this->applicant('cap-pool-'.$index);
        }

        $this->assertSame(5, Employee::whereNull('pharmacy_id')->count());
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function hiringOwner(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    private function asOwner(Pharmacist $owner): static
    {
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        return $this;
    }

    private function applicant(string $suffix, string $role = Employee::ROLE_EMPLOYEE): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '09990'.substr(md5($suffix), 0, 5),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => $role,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
