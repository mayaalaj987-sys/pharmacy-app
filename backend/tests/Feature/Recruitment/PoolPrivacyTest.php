<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\JobOffer;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * What a pharmacist may learn about a job seeker before either side has
 * committed to anything.
 *
 * The old listing handed every approved pharmacist on the platform the name,
 * phone number and email of every applicant, in bulk, from the moment they
 * registered. It also showed no CV, so the one thing a recruiter actually
 * needs to make a decision was the one thing missing.
 */
class PoolPrivacyTest extends SecurityTestCase
{
    public function test_the_pool_shows_a_name_and_a_role_and_no_way_to_contact_anyone(): void
    {
        $applicant = $this->applicant('pool-privacy');
        [$owner] = $this->hiringOwner('pool-privacy');

        $response = $this->asOwner($owner)->getJson('/api/employees/pending')->assertOk();

        $response->assertJsonPath('employees.0.name', $applicant->name)
            ->assertJsonPath('employees.0.role', 'employee')
            ->assertJsonMissingPath('employees.0.phone')
            ->assertJsonMissingPath('employees.0.email')
            // Salary is between an applicant and whoever hires them.
            ->assertJsonMissingPath('employees.0.salary');

        $body = $response->getContent();
        $this->assertStringNotContainsString($applicant->phone, $body);
        $this->assertStringNotContainsString($applicant->email, $body);
    }

    public function test_the_pool_reports_which_documents_exist_without_exposing_them(): void
    {
        $applicant = $this->applicant('pool-docs');
        $this->documentFor($applicant, 'cv');
        [$owner] = $this->hiringOwner('pool-docs');

        $response = $this->asOwner($owner)->getJson('/api/employees/pending')->assertOk();

        $response->assertJsonPath('employees.0.has_cv', true)
            ->assertJsonPath('employees.0.has_experience_proof', false);

        // Availability, not access. Reading the file is a separate, logged call.
        $this->assertStringNotContainsString('storage_key', $response->getContent());
    }

    public function test_a_superseded_document_does_not_count_as_available(): void
    {
        // A recruiter sees the CV this person stands behind today, not a draft
        // they replaced.
        $applicant = $this->applicant('pool-superseded');
        $this->documentFor($applicant, 'cv')->forceFill(['superseded_at' => now()])->save();
        [$owner] = $this->hiringOwner('pool-superseded');

        $this->asOwner($owner)->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonPath('employees.0.has_cv', false);
    }

    public function test_a_pharmacy_sees_its_own_offer_and_never_a_competitors(): void
    {
        $applicant = $this->applicant('pool-offer');
        [$mine, $minePharmacy] = $this->hiringOwner('pool-offer-mine');
        [$rival, $rivalPharmacy] = $this->hiringOwner('pool-offer-rival');

        JobOffer::create([
            'pharmacy_id' => $minePharmacy->id,
            'employee_id' => $applicant->id,
            'created_by_pharmacist_id' => $mine->id,
            'shift' => Employee::SHIFT_MORNING,
            'salary' => 400000,
            'status' => JobOffer::STATUS_PENDING,
            'offered_at' => now(),
        ]);
        JobOffer::create([
            'pharmacy_id' => $rivalPharmacy->id,
            'employee_id' => $applicant->id,
            'created_by_pharmacist_id' => $rival->id,
            'shift' => Employee::SHIFT_EVENING,
            'salary' => 999999,
            'status' => JobOffer::STATUS_PENDING,
            'offered_at' => now(),
        ]);

        $this->asOwner($mine)->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonPath('employees.0.offer.shift', 'morning');

        // The rival's terms must not leak through the shared applicant.
        $this->assertStringNotContainsString(
            '999999',
            $this->asOwner($mine)->getJson('/api/employees/pending')->getContent(),
        );
    }

    public function test_the_pool_reports_the_shifts_this_pharmacy_can_hire_for(): void
    {
        $this->applicant('pool-shifts');
        [$owner, $pharmacy] = $this->hiringOwner('pool-shifts');
        $this->employee($pharmacy, '801', Employee::SHIFT_MORNING);

        $this->asOwner($owner)->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonPath('free_shifts', ['evening'])
            ->assertJsonPath('shifts', ['morning', 'evening']);
    }

    public function test_someone_already_hired_leaves_the_pool(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('pool-hired');
        $hired = $this->applicant('pool-hired-1');
        $stillLooking = $this->applicant('pool-hired-2');

        $this->hire($owner, $pharmacy, $hired)->assertOk();

        $this->asOwner($owner)->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.id', $stillLooking->id);
    }

    public function test_the_pool_is_paginated(): void
    {
        // It used to be an unbounded ->get(): every applicant on the platform in
        // one response, growing without limit.
        foreach (range(1, 30) as $index) {
            $this->applicant('pool-page-'.$index);
        }
        [$owner] = $this->hiringOwner('pool-page');

        $this->asOwner($owner)->getJson('/api/employees/pending')
            ->assertOk()
            ->assertJsonCount(25, 'employees')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 2);

        $this->asOwner($owner)->getJson('/api/employees/pending?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_the_pool_is_closed_to_employees(): void
    {
        [$owner, $pharmacy] = $this->hiringOwner('pool-guard');
        $employee = $this->employee($pharmacy, '802');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/employees/pending')->assertUnauthorized();
        $this->assertTrue($owner->exists);
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
            'phone' => '0955'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }

    private function documentFor(Employee $employee, string $type): EmployeeDocumentVersion
    {
        return $employee->documentVersions()->create([
            'document_type' => $type,
            'version_number' => 1,
            'storage_key' => 'employee-documents/'.$employee->id.'-'.$type.'.pdf',
            'verified_mime_type' => 'application/pdf',
            'byte_size' => 1024,
            'sha256' => hash('sha256', $type.$employee->id),
        ]);
    }
}
