<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;

/**
 * Platform analytics for the administrator console.
 *
 * The figures drive charts an administrator makes decisions from, so the
 * arithmetic is pinned against hand-counted fixtures rather than spot checks.
 */
class AdminAnalyticsTest extends AdminTestCase
{
    public function test_pharmacy_analytics_separate_operating_owners_from_applicants(): void
    {
        // Two branches.
        $multi = $this->owner('multi');
        $this->pharmacyFor($multi, 'multi-a', 'approved');
        $this->pharmacyFor($multi, 'multi-b', 'approved');

        // One branch, plus an unrelated rejected application.
        $single = $this->owner('single');
        $this->pharmacyFor($single, 'single-a', 'approved');
        $this->pharmacyFor($single, 'single-b', 'rejected');

        // Applied but never approved: an owner on paper only.
        $waiting = $this->owner('waiting');
        $this->pharmacyFor($waiting, 'waiting-a', 'pending');

        // No pharmacy at all.
        $this->owner('idle');

        $response = $this->asAdmin($this->admin('analytics'))
            ->getJson('/api/admin/analytics/pharmacies')
            ->assertOk();

        $this->assertSame(4, $response->json('data.total_owners'));
        $this->assertSame(2, $response->json('data.owners_operating'));
        $this->assertSame(2, $response->json('data.owners_without_an_approved_pharmacy'));

        $this->assertSame(3, $response->json('data.branches.approved'));
        $this->assertSame(1, $response->json('data.branches.pending'));
        $this->assertSame(1, $response->json('data.branches.rejected'));

        // One of the two operating owners runs a single branch.
        $this->assertSame(1, $response->json('data.distribution.single_branch_owners'));
        $this->assertSame(1, $response->json('data.distribution.multi_branch_owners'));
        $this->assertSame(50.0, (float) $response->json('data.distribution.single_branch_percentage'));
        $this->assertSame(50.0, (float) $response->json('data.distribution.multi_branch_percentage'));
    }

    public function test_a_deactivated_owner_leaves_the_active_owner_base(): void
    {
        $active = $this->owner('active-owner');
        $this->pharmacyFor($active, 'active-owner', 'approved');
        $this->owner('gone')->forceFill(['deactivated_at' => now()])->save();

        $this->asAdmin($this->admin('deactivated'))
            ->getJson('/api/admin/analytics/pharmacies')
            ->assertOk()
            ->assertJsonPath('data.total_owners', 1);
    }

    public function test_pharmacy_analytics_survive_an_empty_platform(): void
    {
        // Percentages must not divide by zero on a fresh install.
        $this->asAdmin($this->admin('empty'))
            ->getJson('/api/admin/analytics/pharmacies')
            ->assertOk()
            ->assertJsonPath('data.total_owners', 0)
            ->assertJsonPath('data.distribution.single_branch_percentage', 0)
            ->assertJsonPath('data.distribution.multi_branch_percentage', 0);
    }

    public function test_job_market_counts_vacancies_against_the_employee_cap(): void
    {
        $owner = $this->owner('jobs');
        $full = $this->pharmacyFor($owner, 'jobs-full', 'approved');
        $half = $this->pharmacyFor($owner, 'jobs-half', 'approved');
        // A pending pharmacy is not hiring yet.
        $this->pharmacyFor($owner, 'jobs-pending', 'pending');

        $this->employeeFor($full, 'f1', 'approved');
        $this->employeeFor($full, 'f2', 'approved');
        $this->employeeFor($half, 'h1', 'approved');
        $this->employeeFor($half, 'h2', 'pending');
        $this->employeeFor($half, 'h3', 'rejected');

        $response = $this->asAdmin($this->admin('jobs'))
            ->getJson('/api/admin/analytics/job-market')
            ->assertOk();

        // 2 approved pharmacies x cap 2 = 4 slots, 3 filled, 1 vacancy.
        $this->assertSame(1, $response->json('data.open_positions'));
        $this->assertSame(4, $response->json('data.capacity.total_slots'));
        $this->assertSame(3, $response->json('data.capacity.filled_slots'));

        // Only the still-pending applicant is actively seeking.
        $this->assertSame(1, $response->json('data.active_seekers'));
        $this->assertSame(5, $response->json('data.total_applicants'));
        $this->assertSame(3, $response->json('data.hired'));
        $this->assertSame(1, $response->json('data.rejected'));
        $this->assertSame(60.0, (float) $response->json('data.hire_rate_percentage'));
    }

    public function test_job_market_survives_having_no_applicants(): void
    {
        $this->asAdmin($this->admin('no-jobs'))
            ->getJson('/api/admin/analytics/job-market')
            ->assertOk()
            ->assertJsonPath('data.hire_rate_percentage', 0)
            ->assertJsonPath('data.total_applicants', 0);
    }

    public function test_onboarding_returns_twelve_months_including_empty_ones(): void
    {
        $this->owner('now-1');
        $this->owner('now-2');
        $this->owner('older')->forceFill(['created_at' => now()->subMonths(3)])->save();

        $response = $this->asAdmin($this->admin('trend'))
            ->getJson('/api/admin/analytics/onboarding')
            ->assertOk();

        $points = $response->json('data.points');
        $this->assertCount(12, $points, 'A chart needs every month, including the quiet ones.');
        $this->assertSame(3, $response->json('data.total'));

        // Newest bucket last, and it holds this month's two sign-ups.
        $this->assertSame(now()->format('Y-m'), $points[11]['month']);
        $this->assertSame(2, $points[11]['registrations']);
        $this->assertSame(1, $points[8]['registrations']);
        $this->assertSame(0, $points[0]['registrations']);
    }

    public function test_onboarding_never_merges_the_same_month_across_years(): void
    {
        // The reference implementation grouped by month name alone, which folded
        // last year's January into this year's.
        $this->owner('this-year')->forceFill(['created_at' => now()->subMonths(2)])->save();
        $this->owner('last-year')->forceFill(['created_at' => now()->subMonths(14)])->save();

        $response = $this->asAdmin($this->admin('years'))
            ->getJson('/api/admin/analytics/onboarding')
            ->assertOk();

        // Only the in-window sign-up is counted; the 14-month-old one is out.
        $this->assertSame(1, $response->json('data.total'));

        $months = array_column($response->json('data.points'), 'month');
        $this->assertSame($months, array_unique($months), 'Buckets must be unique per year-month.');
    }

    public function test_a_pharmacy_reviewer_may_read_analytics(): void
    {
        // Aggregates expose no single pharmacy's trading data, so the reviewer
        // role is enough — unlike administrator management.
        $this->asAdmin($this->admin('reviewer', Admin::ROLE_PHARMACY_REVIEWER))
            ->getJson('/api/admin/analytics/pharmacies')
            ->assertOk();
    }

    public function test_analytics_are_closed_to_anyone_who_is_not_an_admin(): void
    {
        foreach ([
            '/api/admin/analytics/pharmacies',
            '/api/admin/analytics/job-market',
            '/api/admin/analytics/onboarding',
        ] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_a_disabled_admin_cannot_read_analytics(): void
    {
        $this->asAdmin($this->admin('disabled-analytics', Admin::ROLE_SUPER_ADMIN, active: false))
            ->getJson('/api/admin/analytics/pharmacies')
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

    private function pharmacyFor(Pharmacist $owner, string $suffix, string $status): Pharmacy
    {
        return Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Al-Mazzeh, Damascus',
            'certificate' => '',
            'license' => '',
            'status' => $status,
        ]);
    }

    private function employeeFor(Pharmacy $pharmacy, string $suffix, string $status): Employee
    {
        return Employee::create([
            'pharmacy_id' => $status === 'approved' ? $pharmacy->id : null,
            'name' => 'Employee '.$suffix,
            'phone' => '09300000'.substr(md5($suffix), 0, 2),
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => $status,
            'first_login' => false,
        ]);
    }
}
