<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/** Sending, re-sending and withdrawing offers, and what the applicant sees. */
class JobOfferFlowTest extends SecurityTestCase
{
    public function test_sending_an_offer_reaches_the_applicant_with_everything_they_need(): void
    {
        $applicant = $this->applicant('offer-send');
        [$owner, $pharmacy] = $this->hiringOwner('offer-send');

        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id,
            'shift' => Employee::SHIFT_EVENING,
            'salary' => 500000,
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'offer_sent')
            ->assertJsonPath('offer.shift', 'evening');

        Sanctum::actingAs($applicant, ['*'], 'employee');

        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonPath('counts.actionable', 1)
            ->assertJsonPath('offers.0.shift', 'evening')
            ->assertJsonPath('offers.0.acceptable', true)
            ->assertJsonPath('offers.0.pharmacy.name', $pharmacy->pharmacy_name)
            ->assertJsonPath('offers.0.pharmacy.address', $pharmacy->pharmacy_address)
            // The pharmacy chose to make contact, so its owner is reachable.
            ->assertJsonPath('offers.0.owner.name', $owner->name)
            ->assertJsonPath('offers.0.owner.email', $owner->email)
            ->assertJsonPath('employment', null);

        $this->assertSame(1, Notification::where('employee_id', $applicant->id)
            ->where('type', Notification::TYPE_OFFER_RECEIVED)->count());
    }

    public function test_re_offering_edits_the_one_row_instead_of_stacking_another(): void
    {
        $applicant = $this->applicant('offer-again');
        [$owner] = $this->hiringOwner('offer-again');

        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING, 'salary' => 300000,
        ])->assertCreated();

        // Changing your mind about the shift is an edit, not a competing offer.
        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_EVENING, 'salary' => 450000,
        ])->assertOk()->assertJsonPath('code', 'offer_updated');

        $this->assertSame(1, JobOffer::count());
        $offer = JobOffer::first();
        $this->assertSame('evening', $offer->shift);
        $this->assertSame(450000.0, (float) $offer->salary);
    }

    public function test_two_pharmacies_can_both_be_waiting_on_the_same_person(): void
    {
        $applicant = $this->applicant('offer-two');
        [$first] = $this->hiringOwner('offer-two-a');
        [$second] = $this->hiringOwner('offer-two-b');

        foreach ([$first, $second] as $owner) {
            $this->asOwner($owner)->postJson('/api/recruitment/offers', [
                'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
            ])->assertCreated();
        }

        Sanctum::actingAs($applicant, ['*'], 'employee');
        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonCount(2, 'offers')
            ->assertJsonPath('counts.actionable', 2);
    }

    public function test_an_offer_to_someone_already_hired_is_refused(): void
    {
        [$employer, $employerPharmacy] = $this->hiringOwner('offer-taken-employer');
        $hired = $this->applicant('offer-taken');
        $this->asOwner($employer)->postJson('/api/employees/approve/'.$hired->id, [
            'pharmacy_id' => $employerPharmacy->id,
        ])->assertOk();

        [$late] = $this->hiringOwner('offer-taken-late');

        $this->asOwner($late)->postJson('/api/recruitment/offers', [
            'employee_id' => $hired->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertStatus(409)->assertJsonPath('code', 'employee_not_available');
    }

    public function test_a_covered_shift_cannot_be_offered(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('offer-covered');
        $this->employee($pharmacy, '920', Employee::SHIFT_MORNING);
        $applicant = $this->applicant('offer-covered-1');

        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'shift_taken')
            ->assertJsonPath('free_shifts', ['evening']);
    }

    public function test_withdrawing_tells_the_applicant_and_leaves_a_record(): void
    {
        $applicant = $this->applicant('offer-withdraw');
        [$owner] = $this->hiringOwner('offer-withdraw');

        $id = $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertCreated()->json('offer.id');

        $this->asOwner($owner)->deleteJson('/api/recruitment/offers/'.$id)
            ->assertOk()
            ->assertJsonPath('code', 'offer_withdrawn');

        $this->assertSame(1, Notification::where('employee_id', $applicant->id)
            ->where('type', Notification::TYPE_OFFER_WITHDRAWN)->count());

        Sanctum::actingAs($applicant, ['*'], 'employee');

        // Still listed: offers are a record. Just not actionable.
        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.status', 'withdrawn')
            ->assertJsonPath('offers.0.acceptable', false)
            ->assertJsonPath('offers.0.unavailable_reason', 'offer_withdrawn')
            ->assertJsonPath('counts.actionable', 0);
    }

    public function test_a_pharmacist_cannot_withdraw_another_pharmacys_offer(): void
    {
        $applicant = $this->applicant('offer-cross');
        [$mine] = $this->hiringOwner('offer-cross-mine');
        [$rival] = $this->hiringOwner('offer-cross-rival');

        $id = $this->asOwner($mine)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertCreated()->json('offer.id');

        $this->asOwner($rival)->deleteJson('/api/recruitment/offers/'.$id)->assertForbidden();
        $this->assertSame(JobOffer::STATUS_PENDING, JobOffer::find($id)->status);
    }

    public function test_an_offer_is_not_acceptable_while_its_holder_is_employed(): void
    {
        // Nothing is written to this offer when they take another job. It simply
        // reads as unacceptable, and becomes acceptable again on its own.
        $applicant = $this->applicant('offer-hold');
        [$suitor] = $this->hiringOwner('offer-hold-suitor');
        [$employer, $employerPharmacy] = $this->hiringOwner('offer-hold-employer');

        $this->asOwner($suitor)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertCreated();

        $this->asOwner($employer)->postJson('/api/employees/approve/'.$applicant->id, [
            'pharmacy_id' => $employerPharmacy->id,
        ])->assertOk();

        Sanctum::actingAs($applicant->fresh(), ['*'], 'employee');
        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.status', 'pending')
            ->assertJsonPath('offers.0.acceptable', false)
            ->assertJsonPath('offers.0.unavailable_reason', 'already_employed')
            ->assertJsonPath('employment.shift', 'morning');
    }

    public function test_an_offer_from_a_suspended_pharmacy_reads_as_unavailable(): void
    {
        $applicant = $this->applicant('offer-blocked');
        [$owner, $pharmacy] = $this->hiringOwner('offer-blocked');
        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertCreated();

        $pharmacy->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Licence expired.'])->save();

        Sanctum::actingAs($applicant, ['*'], 'employee');
        $this->getJson('/api/employee/offers')
            ->assertOk()
            ->assertJsonPath('offers.0.acceptable', false)
            ->assertJsonPath('offers.0.unavailable_reason', 'pharmacy_unavailable');
    }

    public function test_the_offer_endpoints_are_closed_to_the_wrong_actor(): void
    {
        $applicant = $this->applicant('offer-guard');
        [$owner] = $this->hiringOwner('offer-guard');

        // Real tokens throughout, and no Sanctum::actingAs anywhere in this
        // test: that helper installs the acting user for every sanctum guard and
        // keeps doing so for the rest of the method, so mixing it with withToken
        // cannot demonstrate cross-guard rejection — the acting user wins.
        $ownerToken = $owner->createToken('pharmacist', ['app'])->plainTextToken;
        $employeeToken = $applicant->createToken('employee', ['app'])->plainTextToken;

        // A pharmacist has no business reading an applicant's inbox.
        $this->withToken($ownerToken)->getJson('/api/employee/offers')->assertUnauthorized();

        // And an applicant cannot send offers, least of all to themselves.
        $this->withToken($employeeToken)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => Employee::SHIFT_MORNING,
        ])->assertUnauthorized();

        $this->withToken($employeeToken)
            ->deleteJson('/api/recruitment/offers/1')
            ->assertUnauthorized();

        $this->assertSame(0, JobOffer::count());
    }

    public function test_an_unknown_shift_is_rejected(): void
    {
        $applicant = $this->applicant('offer-bogus');
        [$owner] = $this->hiringOwner('offer-bogus');

        $this->asOwner($owner)->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id, 'shift' => 'graveyard',
        ])->assertUnprocessable()->assertJsonValidationErrors('shift');
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

    private function applicant(string $suffix): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0922'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
