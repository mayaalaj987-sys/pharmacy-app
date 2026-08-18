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

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$first->id, [
                'pharmacy_id' => $pharmacy->id,
                'shift' => Employee::SHIFT_MORNING,
                'salary' => 500000,
            ])
            ->assertOk()
            ->assertJsonPath('employee.shift', 'morning');

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$second->id, [
                'pharmacy_id' => $pharmacy->id,
                'shift' => Employee::SHIFT_EVENING,
            ])
            ->assertOk()
            ->assertJsonPath('employee.shift', 'evening');

        $this->assertSame([], $pharmacy->fresh()->freeShifts());
    }

    public function test_a_second_person_cannot_take_a_covered_shift(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-clash');
        $held = $this->applicant('cap-clash-1');
        $rejected = $this->applicant('cap-clash-2');

        $this->asOwner($owner)->postJson('/api/employees/approve/'.$held->id, [
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
        ])->assertOk();

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$rejected->id, [
                'pharmacy_id' => $pharmacy->id,
                'shift' => Employee::SHIFT_MORNING,
            ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'shift_taken')
            // The refusal says which shift is still open, so the pharmacist can
            // act on it instead of guessing.
            ->assertJsonPath('free_shifts', ['evening']);

        $this->assertNull($rejected->fresh()->pharmacy_id);
    }

    public function test_a_third_hire_is_refused_once_every_shift_is_covered(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-third');
        foreach (['cap-third-1', 'cap-third-2'] as $suffix) {
            $this->asOwner($owner)->postJson(
                '/api/employees/approve/'.$this->applicant($suffix)->id,
                ['pharmacy_id' => $pharmacy->id],
            )->assertOk();
        }

        $third = $this->applicant('cap-third-3');

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$third->id, ['pharmacy_id' => $pharmacy->id])
            ->assertStatus(400)
            ->assertJsonPath('code', 'shift_taken')
            ->assertJsonPath('free_shifts', []);

        $this->assertSame(2, Employee::where('pharmacy_id', $pharmacy->id)->count());
    }

    public function test_a_client_that_omits_the_shift_gets_the_first_free_one(): void
    {
        // The app in the field does not know about shifts yet. It must keep
        // hiring successfully, and must not be able to double-book by omission.
        [$owner, $pharmacy] = $this->hiringOwner('cap-implicit');

        $this->asOwner($owner)->postJson(
            '/api/employees/approve/'.$this->applicant('cap-implicit-1')->id,
            ['pharmacy_id' => $pharmacy->id],
        )->assertOk()->assertJsonPath('employee.shift', 'morning');

        $this->asOwner($owner)->postJson(
            '/api/employees/approve/'.$this->applicant('cap-implicit-2')->id,
            ['pharmacy_id' => $pharmacy->id],
        )->assertOk()->assertJsonPath('employee.shift', 'evening');
    }

    public function test_an_unknown_shift_is_rejected(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('cap-bogus');

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$this->applicant('cap-bogus-1')->id, [
                'pharmacy_id' => $pharmacy->id,
                'shift' => 'graveyard',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift');
    }

    public function test_a_trainee_may_be_paid(): void
    {
        // The salary a pharmacist typed for a trainee used to be discarded in
        // silence. Whether training is paid is the pharmacy's decision.
        [$owner, $pharmacy] = $this->hiringOwner('cap-trainee');
        $trainee = $this->applicant('cap-trainee-1', Employee::ROLE_TRAINEE);

        $this->asOwner($owner)
            ->postJson('/api/employees/approve/'.$trainee->id, [
                'pharmacy_id' => $pharmacy->id,
                'salary' => 150000,
            ])
            ->assertOk();

        $this->assertSame(150000.0, (float) $trainee->fresh()->salary);
    }

    public function test_two_pharmacies_may_each_cover_the_same_shift(): void
    {
        // The constraint is per pharmacy, not global.
        [$firstOwner, $firstPharmacy] = $this->hiringOwner('cap-a');
        [$secondOwner, $secondPharmacy] = $this->hiringOwner('cap-b');

        foreach ([[$firstOwner, $firstPharmacy, 'cap-a-1'], [$secondOwner, $secondPharmacy, 'cap-b-1']] as [$owner, $pharmacy, $suffix]) {
            $this->asOwner($owner)->postJson(
                '/api/employees/approve/'.$this->applicant($suffix)->id,
                ['pharmacy_id' => $pharmacy->id, 'shift' => Employee::SHIFT_MORNING],
            )->assertOk();
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
