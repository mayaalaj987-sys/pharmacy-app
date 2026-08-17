<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;

/**
 * Administrator announcements.
 *
 * An announcement is fanned out as one ordinary notification per pharmacy, so
 * the app's existing list and unread badge pick it up unchanged. What matters
 * here is who receives it and that a broadcast is all-or-nothing.
 */
class AdminAnnouncementTest extends AdminTestCase
{
    public function test_a_broadcast_reaches_every_approved_pharmacy(): void
    {
        $approvedA = $this->pharmacy('a', 'approved');
        $approvedB = $this->pharmacy('b', 'approved');
        // Neither of these can operate, so neither should be notified.
        $this->pharmacy('pending', 'pending');
        $this->pharmacy('rejected', 'rejected');

        $this->asAdmin($this->admin('broadcast'))
            ->postJson('/api/admin/announcements', [
                'title' => 'Scheduled maintenance',
                'message' => 'The platform will be unavailable on Friday evening.',
                'target' => 'all',
            ])
            ->assertCreated()
            ->assertJsonPath('code', 'announcement_sent')
            ->assertJsonPath('data.recipients', 2);

        $this->assertSame(2, Notification::where('type', 'admin_announcement')->count());
        foreach ([$approvedA, $approvedB] as $pharmacy) {
            $this->assertDatabaseHas('notifications', [
                'pharmacy_id' => $pharmacy->id,
                'type' => 'admin_announcement',
                'title' => 'Scheduled maintenance',
                'is_read' => false,
            ]);
        }
    }

    public function test_an_announcement_can_target_a_single_pharmacy(): void
    {
        $target = $this->pharmacy('target', 'approved');
        $other = $this->pharmacy('other', 'approved');

        $this->asAdmin($this->admin('targeted'))
            ->postJson('/api/admin/announcements', [
                'title' => 'Document reminder',
                'message' => 'Please upload your renewed licence before the end of the month.',
                'target' => 'pharmacy',
                'pharmacy_id' => $target->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.recipients', 1);

        $this->assertDatabaseHas('notifications', ['pharmacy_id' => $target->id]);
        $this->assertDatabaseMissing('notifications', ['pharmacy_id' => $other->id]);
    }

    public function test_targeting_a_pharmacy_requires_naming_one(): void
    {
        $this->pharmacy('needs-id', 'approved');

        $this->asAdmin($this->admin('missing-id'))
            ->postJson('/api/admin/announcements', [
                'title' => 'No recipient',
                'message' => 'This announcement names no pharmacy to deliver to.',
                'target' => 'pharmacy',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pharmacy_id');

        $this->assertSame(0, Notification::count());
    }

    public function test_targeting_an_unapproved_pharmacy_delivers_nothing(): void
    {
        $pending = $this->pharmacy('still-pending', 'pending');

        $this->asAdmin($this->admin('unapproved'))
            ->postJson('/api/admin/announcements', [
                'title' => 'Cannot reach',
                'message' => 'This pharmacy has not been approved and cannot operate.',
                'target' => 'pharmacy',
                'pharmacy_id' => $pending->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'announcement_no_recipients');

        $this->assertSame(0, Notification::count());
    }

    public function test_a_broadcast_with_no_approved_pharmacies_is_refused(): void
    {
        // Nothing sent is better than a silent no-op the sender cannot detect.
        $this->asAdmin($this->admin('empty-platform'))
            ->postJson('/api/admin/announcements', [
                'title' => 'Nobody home',
                'message' => 'There is no approved pharmacy on the platform yet.',
                'target' => 'all',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'announcement_no_recipients');
    }

    public function test_the_title_and_message_are_validated(): void
    {
        $this->pharmacy('validate', 'approved');

        $this->asAdmin($this->admin('validate-announcement'))
            ->postJson('/api/admin/announcements', ['target' => 'all'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'message']);

        $this->asAdmin($this->admin('validate-short'))
            ->postJson('/api/admin/announcements', [
                'title' => 'Hi',
                'message' => 'too short',
                'target' => 'all',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'message']);

        $this->assertSame(0, Notification::count());
    }

    public function test_the_audience_endpoint_lists_only_approved_pharmacies(): void
    {
        $this->pharmacy('listed', 'approved');
        $this->pharmacy('hidden', 'pending');

        $this->asAdmin($this->admin('audience'))
            ->getJson('/api/admin/announcements/audience')
            ->assertOk()
            ->assertJsonPath('data.recipients', 1)
            ->assertJsonCount(1, 'data.pharmacies');
    }

    public function test_announcements_are_closed_to_outsiders_and_disabled_admins(): void
    {
        $this->pharmacy('closed', 'approved');
        $payload = [
            'title' => 'Unauthorised',
            'message' => 'This announcement should never reach anybody at all.',
            'target' => 'all',
        ];

        $this->postJson('/api/admin/announcements', $payload)->assertUnauthorized();

        $this->asAdmin($this->admin('disabled-announcer', Admin::ROLE_SUPER_ADMIN, active: false))
            ->postJson('/api/admin/announcements', $payload)
            ->assertForbidden();

        $this->assertSame(0, Notification::count());
    }

    private function pharmacy(string $suffix, string $status): Pharmacy
    {
        $owner = Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);

        return Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => '',
            'license' => '',
            'status' => $status,
        ]);
    }
}
