<?php

namespace Tests\Feature\Security;

use App\Models\Medicine;
use App\Models\Sale;
use Laravel\Sanctum\Sanctum;

class TrustedActorFieldsTest extends SecurityTestCase
{
    public function test_sale_uses_authenticated_pharmacist_when_client_omits_actor_id(): void
    {
        $pharmacist = $this->pharmacist('sale-owner');
        $pharmacy = $this->pharmacy($pharmacist, 'sale-owner');
        $medicine = $this->medicine($pharmacy->id, 'Sale Medicine');
        Sanctum::actingAs($pharmacist, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();
        $this->assertSame($pharmacist->id, $sale->pharmacist_id);
        $this->assertNull($sale->employee_id);
        $this->assertSame($pharmacy->id, $sale->pharmacy_id);
    }

    public function test_sale_rejects_client_actor_id_that_conflicts_with_token(): void
    {
        $pharmacist = $this->pharmacist('trusted-owner');
        $otherPharmacist = $this->pharmacist('untrusted-owner');
        $pharmacy = $this->pharmacy($pharmacist, 'trusted-owner');
        $medicine = $this->medicine($pharmacy->id, 'Trusted Medicine');
        Sanctum::actingAs($pharmacist, ['*'], 'pharmacist');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'pharmacist_id' => $otherPharmacist->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertForbidden();

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_employee_cannot_request_another_employees_sales(): void
    {
        $pharmacist = $this->pharmacist('employee-sales-owner');
        $pharmacy = $this->pharmacy($pharmacist, 'employee-sales-owner');
        $employee = $this->employee($pharmacy, '201');
        $otherEmployee = $this->employee($pharmacy, '202');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/sale/my-sales?employee_id='.$otherEmployee->id)->assertForbidden();
    }

    private function medicine(int $pharmacyId, string $name): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacyId,
            'name' => $name,
            'category_medicine' => 'Vitamins',
            'cost_price' => 10,
            'selling_price' => 20,
            'quantity' => 5,
            'reorder_level' => 1,
        ]);
    }
}
