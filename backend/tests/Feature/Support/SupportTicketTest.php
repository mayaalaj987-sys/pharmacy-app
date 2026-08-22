<?php

namespace Tests\Feature\Support;

use App\Models\SupportTicket;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The app side of support: raising a ticket and reading your own.
 *
 * The sender is resolved from the token, so the two things worth pinning are
 * that identity cannot be forged and that one sender never sees another's mail.
 */
class SupportTicketTest extends SecurityTestCase
{
    public function test_a_pharmacist_can_raise_a_ticket(): void
    {
        $owner = $this->pharmacist('ticket-owner');
        $pharmacy = $this->pharmacy($owner, 'ticket-owner');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/support/tickets', [
            'subject' => 'Cannot receive an order',
            'message' => 'Receiving a purchase order does not add the stock to my shelves.',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'support_ticket_created')
            ->assertJsonPath('ticket.status', 'open')
            ->assertJsonPath('ticket.admin_response', null);

        $ticket = SupportTicket::sole();
        $this->assertSame($owner->id, $ticket->pharmacist_id);
        $this->assertNull($ticket->employee_id);
        // The pharmacy is attached for context so support knows where to look.
        $this->assertSame($pharmacy->id, $ticket->pharmacy_id);
    }

    public function test_an_employee_can_raise_a_ticket_attributed_to_them(): void
    {
        $owner = $this->pharmacist('ticket-emp');
        $pharmacy = $this->pharmacy($owner, 'ticket-emp');
        $employee = $this->employee($pharmacy, '701');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/support/tickets', [
            'subject' => 'Signed out after a sale',
            'message' => 'The app signs me out right after I complete a sale at the till.',
        ])->assertCreated();

        $ticket = SupportTicket::sole();
        $this->assertSame($employee->id, $ticket->employee_id);
        $this->assertNull($ticket->pharmacist_id);
        $this->assertSame($pharmacy->id, $ticket->pharmacy_id);
    }

    public function test_the_sender_cannot_be_forged(): void
    {
        $owner = $this->pharmacist('forge');
        $pharmacy = $this->pharmacy($owner, 'forge');
        $victim = $this->pharmacist('forge-victim');
        $employee = $this->employee($pharmacy, '702');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/support/tickets', [
            'subject' => 'Impersonation attempt',
            'message' => 'This ticket tries to name somebody else as its sender.',
            'pharmacist_id' => $victim->id,
            'employee_id' => $employee->id,
            'status' => 'resolved',
            'admin_response' => 'Pre-answered by the client.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacist_id', 'employee_id', 'status', 'admin_response']);

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_subject_and_message_are_validated(): void
    {
        $owner = $this->pharmacist('validate');
        $this->pharmacy($owner, 'validate');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/support/tickets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'message']);

        // A message too short to act on is rejected.
        $this->postJson('/api/support/tickets', ['subject' => 'Hi', 'message' => 'help'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'message']);

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_a_sender_only_sees_their_own_tickets(): void
    {
        $mine = $this->pharmacist('mine');
        $this->pharmacy($mine, 'mine');
        $theirs = $this->pharmacist('theirs');
        $this->pharmacy($theirs, 'theirs');

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->postJson('/api/support/tickets', [
            'subject' => 'My own question',
            'message' => 'This message belongs to the first pharmacist only.',
        ])->assertCreated();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($theirs, ['*'], 'pharmacist');
        $this->postJson('/api/support/tickets', [
            'subject' => 'A different question',
            'message' => 'This message belongs to the second pharmacist only.',
        ])->assertCreated();

        $this->getJson('/api/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.subject', 'A different question')
            ->assertJsonPath('open_count', 1);
    }

    public function test_support_stays_reachable_without_an_active_pharmacy(): void
    {
        // Someone whose pharmacy is still pending is exactly who needs support,
        // so these routes sit outside the active-pharmacy gate.
        $owner = $this->pharmacist('no-active');
        $this->pharmacy($owner, 'no-active');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/support/tickets', [
            'subject' => 'Question before approval',
            'message' => 'I would like to ask something while my pharmacy is reviewed.',
        ], ['X-Pharmacy-Id' => '999999'])->assertCreated();
    }

    public function test_support_requires_authentication(): void
    {
        $this->getJson('/api/support/tickets')->assertUnauthorized();
        $this->postJson('/api/support/tickets', [
            'subject' => 'Anonymous',
            'message' => 'A ticket with no authenticated sender at all.',
        ])->assertUnauthorized();
    }

    public function test_rejected_owner_can_contact_support_with_only_the_registration_credential(): void
    {
        $owner = $this->pharmacist('rejected-support');
        $pharmacy = $this->pharmacy($owner, 'rejected-support');
        $pharmacy->forceFill([
            'status' => 'rejected',
            'rejection_reason' => 'The license image is unreadable.',
        ])->save();
        $token = $owner->createToken('registration', ['registration-status'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/registration/support/tickets', [
                'subject' => 'Question about rejection',
                'message' => 'Please tell me which replacement document is acceptable.',
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.status', 'open');

        $ticket = SupportTicket::sole();
        $this->assertSame($owner->id, $ticket->pharmacist_id);
        $this->assertSame($pharmacy->id, $ticket->pharmacy_id);

        $this->withToken($token)
            ->getJson('/api/registration/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets');

        // The credential remains deliberately useless for normal support and
        // every operational endpoint.
        $this->withToken($token)->getJson('/api/support/tickets')->assertForbidden();
        $this->withToken($token)->getJson('/api/medicines')->assertForbidden();
    }
}
