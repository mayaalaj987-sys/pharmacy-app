<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;

/**
 * The console dashboard: headline counts with real month-over-month movement,
 * and the activity feed read out of the audit log.
 */
class AdminDashboardTest extends AdminTestCase
{
    public function test_the_overview_counts_pharmacies_by_status(): void
    {
        $this->pharmacy('a', 'approved');
        $this->pharmacy('b', 'approved');
        $this->pharmacy('c', 'pending');
        $this->pharmacy('d', 'rejected');

        $response = $this->asAdmin($this->admin('overview'))
            ->getJson('/api/admin/analytics/overview')
            ->assertOk();

        $this->assertSame(4, $response->json('data.totals.registered.value'));
        $this->assertSame(2, $response->json('data.totals.approved.value'));
        $this->assertSame(1, $response->json('data.totals.pending.value'));
    }

    public function test_month_over_month_movement_is_reconstructed_from_timestamps(): void
    {
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(3);

        // Registered and approved before this month began.
        $old = $this->pharmacy('old', 'approved');
        $old->forceFill(['created_at' => $lastMonth, 'reviewed_at' => $lastMonth])->save();

        // Registered last month but only approved this month: it counted as
        // pending back then, and counts as approved now.
        $late = $this->pharmacy('late', 'approved');
        $late->forceFill(['created_at' => $lastMonth, 'reviewed_at' => now()])->save();

        // Brand new this month.
        $this->pharmacy('fresh', 'pending');

        $response = $this->asAdmin($this->admin('movement'))
            ->getJson('/api/admin/analytics/overview')
            ->assertOk();

        // Registered: 3 now, 2 then.
        $this->assertSame(3, $response->json('data.totals.registered.value'));
        $this->assertSame(2, $response->json('data.totals.registered.change.from'));
        $this->assertSame(1, $response->json('data.totals.registered.change.delta'));
        $this->assertSame(50.0, (float) $response->json('data.totals.registered.change.percent'));

        // Approved: 2 now, only the old one then.
        $this->assertSame(2, $response->json('data.totals.approved.value'));
        $this->assertSame(1, $response->json('data.totals.approved.change.from'));
    }

    public function test_growth_from_nothing_reports_no_percentage(): void
    {
        // "+100%" from a zero baseline would overstate a single new row.
        $this->pharmacy('first', 'pending');

        $this->asAdmin($this->admin('from-zero'))
            ->getJson('/api/admin/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.registered.change.from', 0)
            ->assertJsonPath('data.totals.registered.change.percent', null);
    }

    public function test_the_overview_lists_pharmacies_with_pending_ones_first(): void
    {
        $this->pharmacy('approved-one', 'approved');
        $this->pharmacy('waiting', 'pending');

        $response = $this->asAdmin($this->admin('list'))
            ->getJson('/api/admin/analytics/overview')
            ->assertOk();

        // Whatever needs attention surfaces at the top.
        $this->assertSame('pending', $response->json('data.pharmacies.0.status'));
        $this->assertSame('Pharmacy waiting', $response->json('data.pharmacies.0.name'));
        $this->assertNotNull($response->json('data.pharmacies.0.owner'));
    }

    public function test_the_activity_feed_reports_actions_that_changed_something(): void
    {
        $admin = $this->admin('activity');
        $pharmacy = $this->pendingPharmacy('feed');

        $this->asAdmin($admin)
            ->postJson("/api/admin/review/applications/{$pharmacy->id}/approve", [
                'review_version' => $pharmacy->fresh()->review_version,
            ])->assertOk();

        $response = $this->asAdmin($admin)
            ->getJson('/api/admin/activity')
            ->assertOk();

        $labels = array_column($response->json('data'), 'label');
        $this->assertContains('approved a pharmacy', $labels);

        // Every page load is audited, but none of it belongs in the feed.
        $actions = array_column($response->json('data'), 'action');
        $this->assertNotContains('admin.review.applications.index', $actions);
        $this->assertNotContains('admin.session.current', $actions);
        $this->assertNotContains('admin.activity.index', $actions);
    }

    public function test_the_activity_feed_keeps_denied_attempts_visible(): void
    {
        // A refused action is worth seeing precisely because it failed.
        $reviewer = $this->admin('denied-feed', Admin::ROLE_PHARMACY_REVIEWER);

        $this->asAdmin($reviewer)
            ->postJson('/api/admin/admins', [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.test',
                'role' => Admin::ROLE_SUPER_ADMIN,
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
            ])
            ->assertForbidden();

        $response = $this->asAdmin($reviewer)
            ->getJson('/api/admin/activity')
            ->assertOk();

        $denied = collect($response->json('data'))->firstWhere('outcome', 'denied');
        $this->assertNotNull($denied, 'A refused attempt must reach the feed.');
        $this->assertStringContainsString('(denied)', $denied['label']);
        $this->assertSame($reviewer->name, $denied['actor']);
    }

    public function test_browsing_the_console_never_reaches_the_feed(): void
    {
        // Regression guard. A denylist of read suffixes let
        // `admin.analytics.overview` through and the feed filled with page
        // visits; the allowlist is what stops that.
        $admin = $this->admin('browsing');

        foreach ([
            '/api/admin/analytics/overview',
            '/api/admin/analytics/pharmacies',
            '/api/admin/analytics/job-market',
            '/api/admin/analytics/onboarding',
            '/api/admin/pharmacies',
            '/api/admin/support/tickets',
            '/api/admin/announcements/audience',
            '/api/admin/review/applications',
        ] as $uri) {
            $this->asAdmin($admin)->getJson($uri)->assertOk();
        }

        $this->asAdmin($admin)
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_activity_feed_names_the_administrator(): void
    {
        $admin = $this->admin('named');
        $pharmacy = $this->pendingPharmacy('named');

        $this->asAdmin($admin)
            ->postJson("/api/admin/review/applications/{$pharmacy->id}/approve", [
                'review_version' => $pharmacy->fresh()->review_version,
            ])->assertOk();

        $response = $this->asAdmin($admin)->getJson('/api/admin/activity')->assertOk();

        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $entry) {
            $this->assertSame($admin->name, $entry['actor']);
        }
    }

    public function test_a_quiet_console_reports_an_empty_feed(): void
    {
        // Reads are all that has happened, and reads are filtered out.
        $this->asAdmin($this->admin('quiet'))
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_honours_a_limit_and_stays_newest_first(): void
    {
        $admin = $this->admin('limited');

        foreach (['one', 'two'] as $suffix) {
            $pharmacy = $this->pendingPharmacy('limit-'.$suffix);
            $this->asAdmin($admin)
                ->postJson("/api/admin/review/applications/{$pharmacy->id}/approve", [
                    'review_version' => $pharmacy->fresh()->review_version,
                ])->assertOk();
        }

        $all = $this->asAdmin($admin)->getJson('/api/admin/activity')->assertOk();
        $this->assertGreaterThanOrEqual(2, count($all->json('data')));

        $limited = $this->asAdmin($admin)
            ->getJson('/api/admin/activity?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // The single entry returned is the newest one.
        $this->assertSame($all->json('data.0.id'), $limited->json('data.0.id'));
    }

    public function test_the_dashboard_is_closed_to_outsiders_and_disabled_admins(): void
    {
        $this->getJson('/api/admin/analytics/overview')->assertUnauthorized();
        $this->getJson('/api/admin/activity')->assertUnauthorized();

        $disabled = $this->admin('disabled-dashboard', Admin::ROLE_SUPER_ADMIN, active: false);
        $this->asAdmin($disabled)->getJson('/api/admin/analytics/overview')->assertForbidden();
        $this->asAdmin($disabled)->getJson('/api/admin/activity')->assertForbidden();
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
