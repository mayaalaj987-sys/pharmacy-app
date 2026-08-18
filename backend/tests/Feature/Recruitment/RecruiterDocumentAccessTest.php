<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\RecruitmentDocumentAccess;
use App\Services\DocumentVersionService;
use App\Services\PrivateDocumentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * A recruiter reading the file of someone they might hire.
 *
 * This is the deferral routes/api.php carried from the beginning: employee
 * documents were self-access only "until a recruitment authorization model is
 * introduced". You cannot offer a stranger a salary on the strength of a name,
 * so the model exists now — with no quota, because the control is attribution
 * rather than scarcity. Every open is recorded and the applicant is told.
 */
class RecruiterDocumentAccessTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_a_recruiter_can_list_and_read_an_applicants_current_documents(): void
    {
        $applicant = $this->applicant('doc-read');
        $cv = $this->uploadFor($applicant, 'cv');
        [$owner] = $this->hiringOwner('doc-read');

        $listing = $this->asOwner($owner)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'cv')
            ->assertJsonPath('data.0.id', $cv->public_id)
            ->assertJsonPath('applicant.name', $applicant->name);

        // The listing never carries the bytes or the way to find them.
        $this->assertStringNotContainsString('storage_key', $listing->getContent());
        $this->assertStringNotContainsString('sha256', $listing->getContent());

        $response = $this->asOwner($owner)->get($listing->json('data.0.preview_url'));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        // A hostile PDF must not be able to reach the network or report a read.
        $this->assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_the_employee_self_route_is_still_closed_to_pharmacists(): void
    {
        // The whole point of adding an ability rather than widening the existing
        // one. If this ever passes, the recruiter change leaked.
        $applicant = $this->applicant('doc-self');
        $cv = $this->uploadFor($applicant, 'cv');
        [$owner] = $this->hiringOwner('doc-self');

        $this->asOwner($owner)
            ->getJson('/api/employee/documents/'.$cv->id.'/download')
            ->assertUnauthorized();

        $this->asOwner($owner)->getJson('/api/employee/documents')->assertUnauthorized();
    }

    public function test_a_superseded_version_is_neither_listed_nor_readable(): void
    {
        // Replacing a CV keeps the old row forever. A recruiter must see the one
        // this person stands behind, not every draft they ever put up.
        $applicant = $this->applicant('doc-superseded');
        $old = $this->uploadFor($applicant, 'cv');
        $this->uploadFor($applicant, 'cv');
        [$owner] = $this->hiringOwner('doc-superseded');

        $this->assertNotNull($old->fresh()->superseded_at);

        $this->asOwner($owner)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version', 2);

        $this->asOwner($owner)->get(
            '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$old->public_id.'/preview'
        )->assertForbidden();
    }

    public function test_a_pharmacist_without_an_approved_pharmacy_cannot_mine_the_pool(): void
    {
        $applicant = $this->applicant('doc-unapproved');
        $this->uploadFor($applicant, 'cv');
        $owner = $this->pharmacist('doc-unapproved');
        $this->pharmacy($owner, 'doc-unapproved', 'pending');

        // Blocked before the policy even runs: no approved pharmacy means no
        // operational context to act in.
        $this->asOwner($owner)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertForbidden();
    }

    public function test_an_applicant_hired_elsewhere_closes_to_everyone_but_their_employer(): void
    {
        $applicant = $this->applicant('doc-hired');
        $this->uploadFor($applicant, 'cv');
        [$employer, $employerPharmacy] = $this->hiringOwner('doc-hired-employer');
        [$outsider] = $this->hiringOwner('doc-hired-outsider');

        $this->asOwner($employer)->postJson('/api/employees/approve/'.$applicant->id, [
            'pharmacy_id' => $employerPharmacy->id,
        ])->assertOk();

        // A stranger loses access the moment somebody else hires them.
        $this->asOwner($outsider)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertNotFound();

        // Their own employer keeps the file they hired on, which would otherwise
        // vanish at the exact moment it became a personnel record.
        $this->asOwner($employer)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_applicant_with_no_documents_is_indistinguishable_from_one_who_does_not_exist(): void
    {
        $applicant = $this->applicant('doc-none');
        [$owner] = $this->hiringOwner('doc-none');

        $this->asOwner($owner)
            ->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_a_document_cannot_be_read_through_the_wrong_applicant(): void
    {
        // The route binds both segments independently, so the parent link is
        // checked by hand — the same idiom the admin document routes use.
        $mine = $this->applicant('doc-mismatch-mine');
        $cv = $this->uploadFor($mine, 'cv');
        $other = $this->applicant('doc-mismatch-other');
        [$owner] = $this->hiringOwner('doc-mismatch');

        $this->asOwner($owner)->get(
            '/api/recruitment/applicants/'.$other->id.'/documents/'.$cv->public_id.'/preview'
        )->assertNotFound();
    }

    public function test_every_open_is_recorded_with_who_and_what(): void
    {
        $applicant = $this->applicant('doc-log');
        $cv = $this->uploadFor($applicant, 'cv');
        [$owner, $pharmacy] = $this->hiringOwner('doc-log');

        $this->asOwner($owner)->get(
            '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/preview'
        )->assertOk();
        $this->asOwner($owner)->get(
            '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/download'
        )->assertOk();

        $accesses = RecruitmentDocumentAccess::orderBy('id')->get();
        $this->assertCount(2, $accesses);
        $this->assertSame(
            [RecruitmentDocumentAccess::ACTION_PREVIEWED, RecruitmentDocumentAccess::ACTION_DOWNLOADED],
            $accesses->pluck('action')->all(),
        );
        $this->assertSame($owner->id, (int) $accesses->first()->pharmacist_id);
        $this->assertSame($pharmacy->id, (int) $accesses->first()->pharmacy_id);
        $this->assertSame($applicant->id, (int) $accesses->first()->employee_id);
        $this->assertSame($cv->id, (int) $accesses->first()->employee_document_version_id);
    }

    public function test_the_applicant_is_told_once_per_pharmacy_per_day(): void
    {
        $applicant = $this->applicant('doc-notify');
        $cv = $this->uploadFor($applicant, 'cv');
        [$first, $firstPharmacy] = $this->hiringOwner('doc-notify-a');
        [$second] = $this->hiringOwner('doc-notify-b');

        // A preview followed by a download is two accesses and one act of
        // interest; telling them twice in a minute is noise, not news.
        foreach (['preview', 'download', 'preview'] as $action) {
            $this->asOwner($first)->get(
                '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/'.$action
            )->assertOk();
        }

        $announcements = Notification::where('employee_id', $applicant->id)
            ->where('type', Notification::TYPE_CV_VIEWED)
            ->get();

        $this->assertCount(1, $announcements);
        $this->assertStringContainsString($firstPharmacy->pharmacy_name, $announcements->first()->message);
        $this->assertNull($announcements->first()->pharmacy_id);

        // A different pharmacy is different news.
        $this->asOwner($second)->get(
            '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/preview'
        )->assertOk();

        $this->assertSame(2, Notification::where('employee_id', $applicant->id)
            ->where('type', Notification::TYPE_CV_VIEWED)->count());
    }

    public function test_a_document_whose_bytes_changed_underneath_is_refused(): void
    {
        $applicant = $this->applicant('doc-tampered');
        $cv = $this->uploadFor($applicant, 'cv');
        [$owner] = $this->hiringOwner('doc-tampered');

        Storage::disk(PrivateDocumentService::DISK)->put($cv->storage_key, 'not what was uploaded');

        $this->asOwner($owner)->get(
            '/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/download'
        )
            ->assertNotFound()
            ->assertJsonPath('code', 'document_unavailable');

        // Nothing is logged and nobody is told about a read that never happened.
        $this->assertSame(0, RecruitmentDocumentAccess::count());
        $this->assertSame(0, Notification::where('type', Notification::TYPE_CV_VIEWED)->count());
    }

    public function test_an_employee_cannot_use_the_recruiter_routes(): void
    {
        $applicant = $this->applicant('doc-guard');
        $cv = $this->uploadFor($applicant, 'cv');
        [, $pharmacy] = $this->hiringOwner('doc-guard');
        $staff = $this->employee($pharmacy, '910');
        Sanctum::actingAs($staff, ['*'], 'employee');

        $this->getJson('/api/recruitment/applicants/'.$applicant->id.'/documents')->assertUnauthorized();
        $this->get('/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/preview')
            ->assertUnauthorized();
    }

    public function test_an_unauthenticated_browser_hit_is_a_401_and_not_a_stack_trace(): void
    {
        // A preview URL is meant to be opened in a viewer, so it arrives without
        // Accept: application/json. That used to take the browser branch of the
        // exception handler, which redirects guests to route('login') — a route
        // this app does not have — producing a 500. With APP_DEBUG on, a 500 is
        // a full stack trace handed to someone holding no credentials at all.
        $applicant = $this->applicant('doc-anon');
        $cv = $this->uploadFor($applicant, 'cv');

        $this->get('/api/recruitment/applicants/'.$applicant->id.'/documents/'.$cv->public_id.'/preview')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
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

    private function uploadFor(Employee $employee, string $type): EmployeeDocumentVersion
    {
        return app(DocumentVersionService::class)->uploadEmployeeDocument(
            $employee,
            $type,
            $this->validPdfUpload($type.'.pdf'),
        );
    }

    private function applicant(string $suffix): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0933'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
