<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Hash;

/**
 * Console-wide search behind the command palette.
 */
class AdminSearchTest extends AdminTestCase
{
    public function test_it_finds_pharmacies_by_name_and_by_address(): void
    {
        $this->pharmacy('Barada', 'approved', 'Al-Mazzeh, Damascus');
        $this->pharmacy('Al-Shahba', 'approved', 'Al-Furqan, Aleppo');

        $admin = $this->admin('search');

        $this->asAdmin($admin)->getJson('/api/admin/search?q=Barada')
            ->assertOk()
            ->assertJsonCount(1, 'data.pharmacies')
            ->assertJsonPath('data.pharmacies.0.title', 'Pharmacy Barada');

        // The address is searchable too, which is how you find a whole city.
        $this->asAdmin($admin)->getJson('/api/admin/search?q=Aleppo')
            ->assertOk()
            ->assertJsonCount(1, 'data.pharmacies')
            ->assertJsonPath('data.pharmacies.0.title', 'Pharmacy Al-Shahba');
    }

    public function test_it_finds_tickets_by_subject_and_body(): void
    {
        $this->ticket('Cannot upload a licence', 'The upload keeps failing at the last step.');
        $this->ticket('Payment question', 'Our settlement for June has not arrived yet.');

        $admin = $this->admin('ticket-search');

        $this->asAdmin($admin)->getJson('/api/admin/search?q=licence')
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.title', 'Cannot upload a licence');

        // Searching the body matters: people describe a problem before naming it.
        $this->asAdmin($admin)->getJson('/api/admin/search?q=settlement')
            ->assertOk()
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.title', 'Payment question');
    }

    public function test_a_single_character_is_not_treated_as_a_search(): void
    {
        // "a" matches almost everything and is a keystroke, not a query.
        $this->pharmacy('Barada', 'approved');

        $this->asAdmin($this->admin('short'))
            ->getJson('/api/admin/search?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'data.pharmacies')
            ->assertJsonCount(0, 'data.tickets');
    }

    public function test_an_empty_query_returns_nothing_rather_than_everything(): void
    {
        $this->pharmacy('Barada', 'approved');

        $this->asAdmin($this->admin('empty-query'))
            ->getJson('/api/admin/search')
            ->assertOk()
            ->assertJsonCount(0, 'data.pharmacies');
    }

    public function test_results_report_the_status_the_console_shows(): void
    {
        $blocked = $this->pharmacy('Suspended', 'approved');
        $blocked->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Licence expired.'])->save();

        $this->asAdmin($this->admin('status'))
            ->getJson('/api/admin/search?q=Suspended')
            ->assertOk()
            // Not "approved": a suspended pharmacy reads as blocked everywhere.
            ->assertJsonPath('data.pharmacies.0.status', 'blocked');
    }

    public function test_open_tickets_are_listed_before_answered_ones(): void
    {
        $answered = $this->ticket('Delivery issue answered', 'Something about a delivery.');
        $answered->forceFill(['status' => SupportTicket::STATUS_RESOLVED])->save();
        $this->ticket('Delivery issue open', 'Something else about a delivery.');

        $this->asAdmin($this->admin('ordering'))
            ->getJson('/api/admin/search?q=delivery')
            ->assertOk()
            ->assertJsonPath('data.tickets.0.status', 'open');
    }

    public function test_results_are_capped_per_group(): void
    {
        foreach (range(1, 8) as $index) {
            $this->pharmacy('Bulk '.$index, 'approved');
        }

        $this->asAdmin($this->admin('capped-search'))
            ->getJson('/api/admin/search?q=Bulk')
            ->assertOk()
            ->assertJsonCount(5, 'data.pharmacies');
    }

    public function test_search_is_closed_to_outsiders_and_disabled_admins(): void
    {
        $this->getJson('/api/admin/search?q=anything')->assertUnauthorized();

        $this->asAdmin($this->admin('disabled-search', Admin::ROLE_SUPER_ADMIN, active: false))
            ->getJson('/api/admin/search?q=anything')
            ->assertForbidden();
    }

    private function pharmacy(string $suffix, string $status, string $address = 'Al-Mazzeh, Damascus'): Pharmacy
    {
        $owner = Pharmacist::create([
            'name' => 'Owner '.$suffix,
            'email' => 'owner-'.str_replace(' ', '-', strtolower($suffix)).'@example.test',
            'password' => Hash::make('password'),
        ]);

        return Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => $address,
            'certificate' => '',
            'license' => '',
            'status' => $status,
        ]);
    }

    private function ticket(string $subject, string $message): SupportTicket
    {
        $owner = Pharmacist::create([
            'name' => 'Sender',
            'email' => 'sender-'.substr(md5($subject), 0, 6).'@example.test',
            'password' => Hash::make('password'),
        ]);

        return SupportTicket::create([
            'pharmacist_id' => $owner->id,
            'subject' => $subject,
            'message' => $message,
            'status' => SupportTicket::STATUS_OPEN,
        ]);
    }
}
