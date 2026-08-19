<?php

namespace Tests\Feature\Auth;

use App\Models\Pharmacist;
use App\Models\PharmacyDocumentVersion;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Two branches per owner, and no more.
 *
 * A pharmacist can plausibly stand behind two counters. Past that the app would
 * be pretending somebody runs a chain from a phone, and every screen here — one
 * active pharmacy at a time, two shifts, one purchase cart — is built for a
 * shop rather than a head office.
 */
class PharmacyLimitTest extends SecurityTestCase
{
    public function test_a_second_pharmacy_is_allowed(): void
    {
        $owner = $this->pharmacist('limit-second');
        $this->pharmacy($owner, 'limit-second');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->add($owner)->assertCreated();

        $this->assertSame(2, $owner->fresh()->pharmacyCount());
    }

    public function test_a_third_pharmacy_is_refused(): void
    {
        $owner = $this->pharmacist('limit-third');
        $this->pharmacy($owner, 'limit-third-a');
        $this->pharmacy($owner, 'limit-third-b');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->add($owner)
            ->assertStatus(409)
            ->assertJsonPath('code', 'pharmacy_limit_reached')
            ->assertJsonPath('limit', 2);

        $this->assertSame(2, $owner->fresh()->pharmacyCount());
    }

    public function test_a_pending_application_still_counts(): void
    {
        // Otherwise queuing five of them makes the limit meaningless the moment
        // an admin works through the list.
        $owner = $this->pharmacist('limit-pending');
        $this->pharmacy($owner, 'limit-pending-a');
        $this->pharmacy($owner, 'limit-pending-b', 'pending');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->add($owner)->assertStatus(409);
    }

    public function test_a_rejected_application_does_not_count_against_them(): void
    {
        // It was refused. Holding a refusal against the owner forever would
        // leave them with one branch and no way to correct the paperwork.
        $owner = $this->pharmacist('limit-rejected');
        $this->pharmacy($owner, 'limit-rejected-a');
        $this->pharmacy($owner, 'limit-rejected-b', 'rejected');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->add($owner)->assertCreated();
    }

    public function test_nothing_is_uploaded_when_the_limit_refuses_it(): void
    {
        // Checked before the documents are stored, so a refusal does not leave
        // two orphaned files on disk.
        $owner = $this->pharmacist('limit-files');
        $this->pharmacy($owner, 'limit-files-a');
        $this->pharmacy($owner, 'limit-files-b');
        $before = PharmacyDocumentVersion::count();

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->add($owner)->assertStatus(409);

        $this->assertSame($before, PharmacyDocumentVersion::count());
    }

    public function test_one_owners_branches_do_not_limit_another(): void
    {
        $full = $this->pharmacist('limit-full');
        $this->pharmacy($full, 'limit-full-a');
        $this->pharmacy($full, 'limit-full-b');

        $fresh = $this->pharmacist('limit-fresh');
        $this->pharmacy($fresh, 'limit-fresh-a');

        Sanctum::actingAs($fresh, ['*'], 'pharmacist');
        $this->add($fresh)->assertCreated();
    }

    private function add(Pharmacist $owner)
    {
        $this->assertTrue($owner->exists);

        return $this->postJson('/api/pharmacy/add', [
            'pharmacy_name' => 'Branch '.uniqid(),
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => $this->validPdfUpload('certificate.pdf'),
            'license' => $this->validPdfUpload('license.pdf'),
        ]);
    }
}
