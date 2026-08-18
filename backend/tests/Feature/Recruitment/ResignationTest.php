<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Task;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Leaving a job, from either side.
 *
 * Employment used to be a one-way door: dismissal hard-deleted the employee and
 * was blocked by a 409 that fired for everyone, so in practice nobody could
 * ever leave. Detaching keeps the person and everything attached to them, which
 * is what makes the offers they were holding worth keeping.
 */
class ResignationTest extends SecurityTestCase
{
    public function test_resigning_frees_the_shift_and_keeps_the_person(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('resign-basic');
        $employee = $this->applicant('resign-basic');
        $this->hire($owner, $pharmacy, $employee, Employee::SHIFT_MORNING, 400000)->assertOk();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');

        $this->postJson('/api/employee/resign')
            ->assertOk()
            ->assertJsonPath('code', 'employment_ended');

        $left = $employee->fresh();
        $this->assertFalse($left->isEmployed());
        $this->assertNull($left->shift);
        $this->assertNull($left->salary);
        $this->assertSame(Employee::STATUS_PENDING, $left->status);

        // The seat is open again, which is the point of freeing it.
        $this->assertSame(['morning', 'evening'], $pharmacy->fresh()->freeShifts());
    }

    public function test_old_offers_become_acceptable_again(): void
    {
        // The reason offers are never cancelled when somebody is hired. This is
        // the behaviour the whole design was arranged around.
        $employee = $this->applicant('resign-offers');
        [$suitor, $suitorPharmacy] = $this->hiringOwner('resign-suitor');
        [$employer, $employerPharmacy] = $this->hiringOwner('resign-employer');

        Sanctum::actingAs($suitor, ['*'], 'pharmacist');
        $waiting = $this->postJson('/api/recruitment/offers', [
            'employee_id' => $employee->id,
            'shift' => Employee::SHIFT_EVENING,
        ])->assertCreated()->json('offer.id');

        $this->hire($employer, $employerPharmacy, $employee)->assertOk();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $held = collect($this->getJson('/api/employee/offers')->json('offers'))
            ->firstWhere('id', $waiting);
        $this->assertFalse($held['acceptable']);

        $this->postJson('/api/employee/resign')->assertOk();

        $freed = collect($this->getJson('/api/employee/offers')->json('offers'))
            ->firstWhere('id', $waiting);
        $this->assertTrue($freed['acceptable']);
        $this->assertNull($freed['unavailable_reason']);

        // And it can actually be taken, same day.
        $this->postJson('/api/employee/offers/'.$waiting.'/accept')->assertOk();
        $this->assertSame($suitorPharmacy->id, (int) $employee->fresh()->pharmacy_id);
    }

    public function test_leaving_takes_the_old_pharmacys_work_with_it(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('resign-tasks');
        $employee = $this->applicant('resign-tasks');
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Restock shelf A',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/tasks')->assertOk()->assertJsonCount(1, 'tasks');

        $this->postJson('/api/employee/resign')->assertOk();

        // The task row survives for the pharmacy's records; the person simply
        // has no operational context any more.
        $this->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/tasks')->assertForbidden();
        $this->assertDatabaseHas('tasks', ['employee_id' => $employee->id]);
    }

    public function test_resigning_works_even_when_the_pharmacy_is_suspended(): void
    {
        // Somebody stuck at a suspended pharmacy is exactly who needs to leave,
        // so this must not sit behind the active-pharmacy gate.
        [$owner, $pharmacy] = $this->hiringOwner('resign-blocked');
        $employee = $this->applicant('resign-blocked');
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        $pharmacy->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Licence expired.'])->save();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $this->assertFalse($employee->fresh()->isEmployed());
    }

    public function test_resigning_twice_is_refused(): void
    {
        $employee = $this->applicant('resign-twice');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/employee/resign')
            ->assertStatus(409)
            ->assertJsonPath('code', 'not_employed');
    }

    public function test_both_sides_are_told_who_left(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('resign-notify');
        $employee = $this->applicant('resign-notify');
        $this->hire($owner, $pharmacy, $employee, Employee::SHIFT_EVENING)->assertOk();

        Sanctum::actingAs($employee->fresh(), ['*'], 'employee');
        $this->postJson('/api/employee/resign')->assertOk();

        $told = Notification::where('pharmacy_id', $pharmacy->id)
            ->where('type', 'employee_left')->first();
        $this->assertNotNull($told);
        $this->assertStringContainsString('evening', $told->message);

        // Resigning is their own act, so they are not told about it.
        $this->assertSame(0, Notification::where('employee_id', $employee->id)
            ->where('type', Notification::TYPE_EMPLOYMENT_ENDED)->count());
    }

    public function test_a_dismissed_employee_is_told_and_lands_back_in_the_pool(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('dismiss-notify');
        $employee = $this->applicant('dismiss-notify');
        $this->hire($owner, $pharmacy, $employee)->assertOk();

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->deleteJson('/api/employees/'.$employee->id.'/dismiss')
            ->assertOk()
            ->assertJsonPath('code', 'employee_detached');

        // Unlike resigning, this happened to them, so they are told.
        $this->assertSame(1, Notification::where('employee_id', $employee->id)
            ->where('type', Notification::TYPE_EMPLOYMENT_ENDED)->count());

        $this->assertFalse($employee->fresh()->isEmployed());
        $this->assertSame(1, JobOffer::where('employee_id', $employee->id)
            ->where('status', JobOffer::STATUS_ACCEPTED)->count());
    }

    public function test_dismissing_someone_who_does_not_work_here_is_refused(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('dismiss-stranger');
        $this->employee($pharmacy, '930');
        $stranger = $this->applicant('dismiss-stranger-1');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->deleteJson('/api/employees/'.$stranger->id.'/dismiss')->assertForbidden();
    }

    public function test_a_pharmacist_cannot_resign_on_someones_behalf(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('resign-guard');
        $this->employee($pharmacy, '931');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/employee/resign')->assertUnauthorized();
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function hiringOwner(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    private function applicant(string $suffix): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0911'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
