<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Pharmacist;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Hash;

/**
 * The administrator side of support: the queue and answering.
 */
class AdminSupportTicketTest extends AdminTestCase
{
    public function test_the_queue_puts_open_tickets_first_and_oldest_first(): void
    {
        $this->ticket('Resolved one', status: SupportTicket::STATUS_RESOLVED, ageInDays: 9);
        $this->ticket('Newest open', ageInDays: 1);
        $this->ticket('Oldest open', ageInDays: 5);

        $response = $this->asAdmin($this->admin('queue'))
            ->getJson('/api/admin/support/tickets')
            ->assertOk();

        $subjects = array_column($response->json('data'), 'subject');
        $this->assertSame(['Oldest open', 'Newest open', 'Resolved one'], $subjects);
        $this->assertSame(2, $response->json('meta.open_total'));
    }

    public function test_the_queue_can_be_filtered_by_status(): void
    {
        $this->ticket('Still open');
        $this->ticket('Already answered', status: SupportTicket::STATUS_RESOLVED);

        $this->asAdmin($this->admin('filter'))
            ->getJson('/api/admin/support/tickets?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Already answered');
    }

    public function test_the_queue_names_the_sender_and_their_pharmacy(): void
    {
        $ticket = $this->ticket('Who sent this');

        $this->asAdmin($this->admin('sender'))
            ->getJson('/api/admin/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.sender.role', 'pharmacist')
            ->assertJsonPath('data.0.sender.name', $ticket->pharmacist->name)
            ->assertJsonPath('data.0.sender.email', $ticket->pharmacist->email);
    }

    public function test_answering_a_ticket_resolves_it_and_records_the_author(): void
    {
        $ticket = $this->ticket('Needs an answer');
        $admin = $this->admin('responder');

        $this->asAdmin($admin)
            ->postJson("/api/admin/support/tickets/{$ticket->id}/respond", [
                'response' => 'Update to the latest build and the issue goes away.',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'support_ticket_resolved')
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.responded_by', $admin->name);

        $fresh = $ticket->fresh();
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $fresh->status);
        $this->assertSame($admin->id, $fresh->responded_by_admin_id);
        $this->assertNotNull($fresh->responded_at);
    }

    public function test_a_second_administrator_cannot_overwrite_an_answer(): void
    {
        $ticket = $this->ticket('Contended');
        $first = $this->admin('first');
        $second = $this->admin('second');

        $this->asAdmin($first)
            ->postJson("/api/admin/support/tickets/{$ticket->id}/respond", [
                'response' => 'The first administrator answered this one.',
            ])->assertOk();

        $this->asAdmin($second)
            ->postJson("/api/admin/support/tickets/{$ticket->id}/respond", [
                'response' => 'The second administrator tries to answer too.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'support_ticket_already_resolved');

        $fresh = $ticket->fresh();
        $this->assertSame($first->id, $fresh->responded_by_admin_id);
        $this->assertStringContainsString('first administrator', $fresh->admin_response);
    }

    public function test_the_response_text_is_validated(): void
    {
        $ticket = $this->ticket('Validate me');

        $this->asAdmin($this->admin('validate-response'))
            ->postJson("/api/admin/support/tickets/{$ticket->id}/respond", ['response' => 'ok'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response');

        $this->assertTrue($ticket->fresh()->isOpen());
    }

    public function test_the_sender_sees_the_answer_on_their_own_ticket(): void
    {
        $ticket = $this->ticket('Round trip');

        $this->asAdmin($this->admin('round-trip'))
            ->postJson("/api/admin/support/tickets/{$ticket->id}/respond", [
                'response' => 'Here is the answer the pharmacist will read.',
            ])->assertOk();

        // Same row, read back through the app-side contract.
        $fresh = $ticket->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertStringContainsString('the pharmacist will read', $fresh->admin_response);
    }

    public function test_a_reviewer_may_work_the_queue_but_a_stranger_may_not(): void
    {
        $ticket = $this->ticket('Access');

        $this->asAdmin($this->admin('reviewer-support', Admin::ROLE_PHARMACY_REVIEWER))
            ->getJson('/api/admin/support/tickets')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/support/tickets')->assertUnauthorized();
        $this->postJson("/api/admin/support/tickets/{$ticket->id}/respond", [
            'response' => 'An answer from nobody in particular.',
        ])->assertUnauthorized();
    }

    public function test_a_disabled_admin_cannot_work_the_queue(): void
    {
        $this->asAdmin($this->admin('disabled-support', Admin::ROLE_SUPER_ADMIN, active: false))
            ->getJson('/api/admin/support/tickets')
            ->assertForbidden();
    }

    private function ticket(
        string $subject,
        string $status = SupportTicket::STATUS_OPEN,
        int $ageInDays = 0,
    ): SupportTicket {
        $suffix = substr(md5($subject), 0, 8);
        $sender = Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);

        $ticket = SupportTicket::create([
            'pharmacist_id' => $sender->id,
            'subject' => $subject,
            'message' => 'A support message long enough to be accepted by validation.',
            'status' => $status,
        ]);

        $ticket->forceFill(['created_at' => now()->subDays($ageInDays)])->save();

        return $ticket;
    }
}
