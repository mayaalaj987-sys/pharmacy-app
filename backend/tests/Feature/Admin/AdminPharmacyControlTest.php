<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Rating;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * Pharmacy control: the operational register and suspension.
 *
 * Suspension is a separate axis from the review status, so the tests that
 * matter most are the ones proving a suspended pharmacy really stops trading
 * and gets its approval back intact when restored.
 */
class AdminPharmacyControlTest extends AdminTestCase
{
    public function test_the_register_reports_owner_branches_and_the_app_rating(): void
    {
        $owner = $this->owner('rated');
        $this->pharmacyFor($owner, 'branch-a', 'approved');
        $this->pharmacyFor($owner, 'branch-b', 'approved');
        // The stars are the owner's rating *of the app*, not a customer review.
        Rating::create(['pharmacist_id' => $owner->id, 'stars' => 4, 'date' => now()->toDateString()]);

        $response = $this->asAdmin($this->admin('register'))
            ->getJson('/api/admin/pharmacies')
            ->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('data.0.owner.branches'));
        $this->assertSame(4, $response->json('data.0.owner.app_rating'));
        $this->assertSame($owner->name, $response->json('data.0.owner.name'));
    }

    public function test_an_owner_who_never_rated_the_app_reports_no_rating(): void
    {
        $owner = $this->owner('unrated');
        $this->pharmacyFor($owner, 'unrated', 'approved');

        // Null, not zero: "not rated" and "rated zero" are different things.
        $this->asAdmin($this->admin('unrated'))
            ->getJson('/api/admin/pharmacies')
            ->assertOk()
            ->assertJsonPath('data.0.owner.app_rating', null);
    }

    public function test_the_register_can_be_searched_and_filtered(): void
    {
        $owner = $this->owner('filters');
        $this->pharmacyFor($owner, 'Barada', 'approved');
        $this->pharmacyFor($owner, 'Qasioun', 'pending');

        $this->asAdmin($this->admin('search'))
            ->getJson('/api/admin/pharmacies?search=Barada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pharmacy Barada');

        $this->asAdmin($this->admin('filter-status'))
            ->getJson('/api/admin/pharmacies?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pharmacy Qasioun');
    }

    public function test_suspending_a_pharmacy_stops_it_trading(): void
    {
        $owner = $this->owner('suspend');
        $pharmacy = $this->pharmacyFor($owner, 'suspend', 'approved');
        $medicine = $this->stock($pharmacy);

        // It trades normally first.
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertOk();
        $this->app['auth']->forgetGuards();

        $this->asAdmin($this->admin('suspender'))
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", [
                'reason' => 'Licence expired and has not been renewed.',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'pharmacy_blocked');

        // The approval survives; only trading stops.
        $fresh = $pharmacy->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertTrue($fresh->isBlocked());
        $this->assertFalse($fresh->isOperational());

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertForbidden();
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertForbidden();
    }

    public function test_an_employee_of_a_suspended_pharmacy_is_stopped_too(): void
    {
        $owner = $this->owner('emp-suspend');
        $pharmacy = $this->pharmacyFor($owner, 'emp-suspend', 'approved');
        $employee = Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'shift' => Employee::SHIFT_MORNING,
            'name' => 'Employee',
            'phone' => '0930111222',
            'email' => 'employee-suspend@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'approved',
            'first_login' => false,
        ]);

        $this->asAdmin($this->admin('emp-suspender'))
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", [
                'reason' => 'Under investigation by the regulator.',
            ])->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee, ['*'], 'employee');
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertForbidden();
    }

    public function test_restoring_a_pharmacy_gives_back_full_access(): void
    {
        $owner = $this->owner('restore');
        $pharmacy = $this->pharmacyFor($owner, 'restore', 'approved');
        $admin = $this->admin('restorer');

        $this->asAdmin($admin)
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", ['reason' => 'Temporary hold.'])
            ->assertOk();

        $this->asAdmin($admin)
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/unblock")
            ->assertOk()
            ->assertJsonPath('code', 'pharmacy_unblocked');

        $fresh = $pharmacy->fresh();
        $this->assertFalse($fresh->isBlocked());
        $this->assertTrue($fresh->isOperational());
        $this->assertNull($fresh->blocked_reason);
        $this->assertNull($fresh->blocked_by_admin_id);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertOk();
    }

    public function test_a_suspension_records_its_reason_and_author(): void
    {
        $owner = $this->owner('audited');
        $pharmacy = $this->pharmacyFor($owner, 'audited', 'approved');
        $admin = $this->admin('author');

        $this->asAdmin($admin)
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", [
                'reason' => 'Repeated expired stock reported by customers.',
            ])->assertOk();

        $fresh = $pharmacy->fresh();
        $this->assertSame($admin->id, $fresh->blocked_by_admin_id);
        $this->assertStringContainsString('expired stock', $fresh->blocked_reason);
        $this->assertNotNull($fresh->blocked_at);
    }

    public function test_only_an_approved_pharmacy_can_be_suspended(): void
    {
        $owner = $this->owner('pending-block');
        $pending = $this->pharmacyFor($owner, 'pending-block', 'pending');

        $this->asAdmin($this->admin('pending-blocker'))
            ->postJson("/api/admin/pharmacies/{$pending->id}/block", ['reason' => 'Not applicable.'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pharmacy_not_approved');

        $this->assertFalse($pending->fresh()->isBlocked());
    }

    public function test_double_suspension_and_pointless_restore_are_refused(): void
    {
        $owner = $this->owner('double');
        $pharmacy = $this->pharmacyFor($owner, 'double', 'approved');
        $first = $this->admin('double-first');

        $this->asAdmin($first)
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", ['reason' => 'The first reason.'])
            ->assertOk();

        // A second administrator must not silently overwrite the first reason.
        $this->asAdmin($this->admin('double-second'))
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", ['reason' => 'A different reason.'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'pharmacy_already_blocked');

        $this->assertSame($first->id, $pharmacy->fresh()->blocked_by_admin_id);

        $this->asAdmin($first)->postJson("/api/admin/pharmacies/{$pharmacy->id}/unblock")->assertOk();
        $this->asAdmin($first)
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/unblock")
            ->assertStatus(409)
            ->assertJsonPath('code', 'pharmacy_not_blocked');
    }

    public function test_a_suspension_needs_a_reason(): void
    {
        $owner = $this->owner('no-reason');
        $pharmacy = $this->pharmacyFor($owner, 'no-reason', 'approved');

        $this->asAdmin($this->admin('no-reason'))
            ->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", ['reason' => 'no'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertFalse($pharmacy->fresh()->isBlocked());
    }

    public function test_pharmacy_control_is_closed_to_outsiders_and_disabled_admins(): void
    {
        $owner = $this->owner('closed');
        $pharmacy = $this->pharmacyFor($owner, 'closed', 'approved');

        $this->getJson('/api/admin/pharmacies')->assertUnauthorized();
        $this->postJson("/api/admin/pharmacies/{$pharmacy->id}/block", ['reason' => 'Nope.'])
            ->assertUnauthorized();

        $this->asAdmin($this->admin('disabled-control', Admin::ROLE_SUPER_ADMIN, active: false))
            ->getJson('/api/admin/pharmacies')
            ->assertForbidden();

        $this->assertFalse($pharmacy->fresh()->isBlocked());
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

    private function stock(Pharmacy $pharmacy): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Paracetamol 500mg',
            'category_medicine' => 'Painkillers',
            'cost_price' => 3000,
            'selling_price' => 6000,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => 20,
            'reorder_level' => 5,
            'expire_date' => now()->addYear()->toDateString(),
        ]);
    }
}
