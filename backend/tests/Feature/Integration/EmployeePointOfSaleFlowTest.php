<?php

namespace Tests\Feature\Integration;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The employee point-of-sale round trip, end to end.
 *
 * Guards a shipped bug: after recording a sale the client refreshed its sales
 * summary from `GET /sale/all`, which is pharmacist-only. Employees got a 401
 * there, and the Flutter auth interceptor reads any 401 as an expired session,
 * so the employee was signed out immediately after a sale that had already
 * been committed.
 *
 * These tests pin both halves of the contract: everything the point of sale
 * legitimately needs stays reachable for an employee, and the report endpoint
 * that caused the sign-out really is the one that rejects them.
 */
class EmployeePointOfSaleFlowTest extends SecurityTestCase
{
    public function test_employee_can_sell_and_keep_working_without_any_401(): void
    {
        [$pharmacy, $employee] = $this->workspace('pos-flow');
        $medicine = $this->stock($pharmacy, 'Amoxicillin 500mg', quantity: 40, sell: 12500);
        Sanctum::actingAs($employee, ['*'], 'employee');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        // Everything the POS screen touches, in the order the app touches it.
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, $headers)->assertOk();

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 3]],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('total_price', 37500);

        // The refresh POS performs after a sale, plus the rest of the shell.
        foreach ([
            '/api/medicines?pharmacy_id='.$pharmacy->id,
            '/api/notifications?pharmacy_id='.$pharmacy->id,
            '/api/sale/my-sales?employee_id='.$employee->id,
            '/api/tasks',
            '/api/me',
        ] as $uri) {
            $this->getJson($uri, $headers)
                ->assertOk()
                ->assertStatus(200);
        }

        $this->assertSame(37, $medicine->fresh()->quantity);
    }

    public function test_the_sales_report_is_the_endpoint_that_signs_an_employee_out(): void
    {
        [$pharmacy, $employee] = $this->workspace('pos-401');
        Sanctum::actingAs($employee, ['*'], 'employee');

        // 401 is what the interceptor treats as "session expired". This asserts
        // the exact status, unlike the role matrix which accepts 401/403/404.
        $this->getJson('/api/sale/all?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertStatus(401);

        $this->getJson('/api/sale/daily?pharmacy_id='.$pharmacy->id, ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertStatus(401);
    }

    public function test_a_sale_is_still_recorded_even_though_the_report_is_denied(): void
    {
        [$pharmacy, $employee] = $this->workspace('pos-committed');
        $medicine = $this->stock($pharmacy, 'Paracetamol 500mg', quantity: 10, sell: 6000);
        Sanctum::actingAs($employee, ['*'], 'employee');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2]],
        ], $headers)->assertCreated();

        // Denied report, exactly as the buggy client did.
        $this->getJson('/api/sale/all?pharmacy_id='.$pharmacy->id, $headers)->assertStatus(401);

        // The sale survives, and the employee's own report still shows it.
        $this->assertDatabaseHas('sales', [
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
        ]);

        $this->getJson('/api/sale/my-sales?employee_id='.$employee->id, $headers)
            ->assertOk()
            ->assertJsonPath('total_sales', 1);
    }

    public function test_an_insurance_sale_returns_the_discounted_total_the_receipt_shows(): void
    {
        [$pharmacy, $employee] = $this->workspace('pos-insurance');
        $medicine = $this->stock($pharmacy, 'Vitamin C 1000mg', quantity: 20, sell: 10000);
        Sanctum::actingAs($employee, ['*'], 'employee');

        // 2 x 10000 = 20000, less the 20% insurance discount applied server-side.
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'insurance',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])
            ->assertCreated()
            ->assertJsonPath('total_price', 16000);
    }

    public function test_a_pharmacist_selling_at_the_point_of_sale_is_unaffected(): void
    {
        $owner = $this->pharmacist('pos-owner');
        $pharmacy = $this->pharmacy($owner, 'pos-owner');
        $medicine = $this->stock($pharmacy, 'Aspirin 100mg', quantity: 15, sell: 4000);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ], $headers)->assertCreated();

        // The owner keeps full access to the report the employee cannot see.
        $this->getJson('/api/sale/all?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('total_sales', 1);
    }

    /** @return array{0:Pharmacy,1:Employee} */
    private function workspace(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);
        $pharmacy = $this->pharmacy($owner, $suffix);
        $employee = $this->employee($pharmacy, substr(md5($suffix), 0, 3));

        return [$pharmacy, $employee];
    }

    private function stock(Pharmacy $pharmacy, string $name, int $quantity, float $sell): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => 'Painkillers',
            'cost_price' => $sell / 2,
            'selling_price' => $sell,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => 5,
            'expire_date' => now()->addYear()->toDateString(),
            'qr_code' => (string) random_int(100000, 999999),
        ]);
    }
}
