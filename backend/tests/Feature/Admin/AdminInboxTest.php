<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Hash;

/**
 * The notification bell.
 *
 * It is derived from outstanding work rather than stored, so the thing worth
 * proving is that the count always matches the queues it summarises — and that
 * work already dealt with drops out of it on its own.
 */
class AdminInboxTest extends AdminTestCase
{
    public function test_the_bell_counts_pending_applications_and_open_tickets(): void
    {
        $this->pharmacy('waiting-a', 'pending');
        $this->pharmacy('waiting-b', 'pending');
        $this->ticket('Cannot upload a licence');

        $response = $this->asAdmin($this->admin('bell'))
            ->getJson('/api/admin/inbox')
            ->assertOk();

        $this->assertSame(3, $response->json('data.total'));
        $this->assertSame(2, $response->json('data.groups.pharmacy_applications'));
        $this->assertSame(1, $response->json('data.groups.support_tickets'));
    }

    public function test_only_work_that_is_still_outstanding_is_counted(): void
    {
        // Already handled, so no longer anybody's problem.
        $this->pharmacy('approved', 'approved');
        $this->pharmacy('rejected', 'rejected');
        $this->ticket('Answered already', SupportTicket::STATUS_RESOLVED);

        $this->asAdmin($this->admin('handled'))
            ->getJson('/api/admin/inbox')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_the_bell_empties_itself_once_the_work_is_done(): void
    {
        // No "mark as read": reviewing the application is what clears it.
        $admin = $this->admin('self-clearing');
        $pharmacy = $this->pendingPharmacy('self-clearing');

        $this->asAdmin($admin)->getJson('/api/admin/inbox')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->asAdmin($admin)
            ->postJson("/api/admin/review/applications/{$pharmacy->id}/approve", [
                'review_version' => $pharmacy->fresh()->review_version,
            ])->assertOk();

        $this->asAdmin($admin)->getJson('/api/admin/inbox')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_entries_name_what_happened_and_who_it_came_from(): void
    {
        $this->pharmacy('named', 'pending');
        $this->ticket('Payment settlement delay');

        $response = $this->asAdmin($this->admin('named-entries'))
            ->getJson('/api/admin/inbox')
            ->assertOk();

        $items = collect($response->json('data.items'));

        $application = $items->firstWhere('kind', 'pharmacy_application');
        $this->assertSame('Pharmacy named', $application['title']);
        $this->assertStringContainsString('Applied for verification', $application['detail']);

        $ticket = $items->firstWhere('kind', 'support_ticket');
        $this->assertSame('Payment settlement delay', $ticket['title']);
        $this->assertStringContainsString('Support request', $ticket['detail']);
    }

    public function test_entries_are_newest_first_across_both_kinds(): void
    {
        $old = $this->pharmacy('older', 'pending');
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $ticket = $this->ticket('Newer request');
        $ticket->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->asAdmin($this->admin('ordering'))
            ->getJson('/api/admin/inbox')
            ->assertOk();

        // The ticket is newer, so it leads regardless of its kind.
        $this->assertSame('support_ticket', $response->json('data.items.0.kind'));
        $this->assertSame('pharmacy_application', $response->json('data.items.1.kind'));
    }

    public function test_the_preview_is_capped_while_the_count_stays_true(): void
    {
        foreach (range(1, 7) as $index) {
            $this->pharmacy('bulk-'.$index, 'pending');
        }

        $response = $this->asAdmin($this->admin('capped'))
            ->getJson('/api/admin/inbox')
            ->assertOk();

        // The dropdown lists a few; the badge still tells the truth.
        $this->assertCount(5, $response->json('data.items'));
        $this->assertSame(7, $response->json('data.total'));
    }

    public function test_a_ticket_from_an_employee_names_the_employee(): void
    {
        $owner = $this->owner('emp-owner');
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy emp',
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => '',
            'license' => '',
            'status' => 'approved',
        ]);
        $employee = Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Lina Haddad',
            'phone' => '0930111222',
            'email' => 'lina@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'approved',
            'first_login' => false,
        ]);
        SupportTicket::create([
            'employee_id' => $employee->id,
            'subject' => 'Signed out after a sale',
            'message' => 'The app signed me out right after completing a sale.',
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $this->asAdmin($this->admin('emp-ticket'))
            ->getJson('/api/admin/inbox')
            ->assertOk()
            ->assertJsonPath('data.items.0.detail', 'Support request · Lina Haddad');
    }

    public function test_the_bell_is_closed_to_outsiders_and_disabled_admins(): void
    {
        $this->getJson('/api/admin/inbox')->assertUnauthorized();

        $this->asAdmin($this->admin('disabled-bell', Admin::ROLE_SUPER_ADMIN, active: false))
            ->getJson('/api/admin/inbox')
            ->assertForbidden();
    }

    private function owner(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function pharmacy(string $suffix, string $status): Pharmacy
    {
        return Pharmacy::create([
            'pharmacist_id' => $this->owner($suffix)->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => '',
            'license' => '',
            'status' => $status,
        ]);
    }

    private function ticket(string $subject, string $status = SupportTicket::STATUS_OPEN): SupportTicket
    {
        return SupportTicket::create([
            'pharmacist_id' => $this->owner(substr(md5($subject), 0, 6))->id,
            'subject' => $subject,
            'message' => 'A support message long enough to satisfy validation.',
            'status' => $status,
        ]);
    }
}
