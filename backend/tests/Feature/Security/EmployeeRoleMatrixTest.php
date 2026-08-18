<?php

namespace Tests\Feature\Security;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacy;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

/**
 * Locks the employee capability matrix against accidental widening.
 * The backend is the source of truth for what the employee UI may expose.
 */
class EmployeeRoleMatrixTest extends SecurityTestCase
{
    public function test_employee_may_read_medicines(): void
    {
        [$pharmacy, $employee] = $this->pharmacyWithEmployee('emp-read');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id)->assertOk();
    }

    public function test_employee_may_create_a_sale_and_stock_decrements(): void
    {
        [$pharmacy, $employee] = $this->pharmacyWithEmployee('emp-sale');
        $medicine = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Paracetamol 500mg',
            'category_medicine' => 'Painkillers',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'quantity' => 10,
            'manufacturer' => 'Demo Labs',
            'reorder_level' => 2,
            'expire_date' => now()->addYear()->toDateString(),
            'qr_code' => '9001',
        ]);
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(7, $medicine->fresh()->quantity);
        // The sale is attributed to the employee, never to a client-supplied id.
        $this->assertDatabaseHas('sales', [
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'pharmacist_id' => null,
        ]);
    }

    public function test_employee_may_read_their_own_sales_and_tasks_and_notifications(): void
    {
        [$pharmacy, $employee] = $this->pharmacyWithEmployee('emp-own');
        Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Check fridge',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/sale/my-sales?employee_id='.$employee->id)->assertOk();
        $this->getJson('/api/tasks')->assertOk();
        $this->getJson('/api/notifications?pharmacy_id='.$pharmacy->id)->assertOk();
    }

    public function test_employee_may_mark_a_single_notification_read(): void
    {
        [$pharmacy, $employee] = $this->pharmacyWithEmployee('emp-notif');
        $notification = Notification::create([
            'pharmacy_id' => $pharmacy->id,
            'title' => 'Sale completed',
            'message' => 'A sale was recorded.',
            'type' => 'sale',
            'is_read' => false,
            'date' => now(),
        ]);
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_employee_is_denied_pharmacist_only_capabilities(): void
    {
        [$pharmacy, $employee] = $this->pharmacyWithEmployee('emp-denied');
        $medicine = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 800,
            'selling_price' => 1250,
            'quantity' => 5,
            'manufacturer' => 'Demo Labs',
            'reorder_level' => 2,
            'expire_date' => now()->addYear()->toDateString(),
            'qr_code' => '9002',
        ]);
        $notification = Notification::create([
            'pharmacy_id' => $pharmacy->id,
            'title' => 'Sale completed',
            'message' => 'A sale was recorded.',
            'type' => 'sale',
            'is_read' => false,
            'date' => now(),
        ]);

        $denied = [
            ['post', '/api/notifications/read-all', ['pharmacy_id' => $pharmacy->id]],
            ['delete', '/api/notifications/'.$notification->id, []],
            ['get', '/api/employees/pending', []],
            ['get', '/api/employees/'.$pharmacy->id, []],
            ['post', '/api/recruitment/offers', ['employee_id' => $employee->id, 'shift' => 'morning']],
            ['delete', '/api/recruitment/offers/1', []],
            ['get', '/api/recruitment/applicants/'.$employee->id.'/documents', []],
            ['delete', '/api/employees/'.$employee->id.'/dismiss', []],
            ['post', '/api/pharmacy/profile/update', ['pharmacy_name' => 'Hacked']],
            ['post', '/api/medicines/add', ['name' => 'X']],
            ['post', '/api/medicines/edit/'.$medicine->id, ['name' => 'X']],
            ['get', '/api/sale/all', []],
            ['get', '/api/reports/dashboard', []],
            ['post', '/api/tasks', ['employee_id' => $employee->id, 'title' => 'X']],
            ['get', '/api/tasks/pharmacy', []],
            ['get', '/api/suppliers', []],
            ['post', '/api/orders', []],
        ];

        foreach ($denied as [$method, $uri, $payload]) {
            Sanctum::actingAs($employee, ['*'], 'employee');
            $response = $this->json(strtoupper($method), $uri, $payload);

            $this->assertContains(
                $response->status(),
                [401, 403, 404],
                $method.' '.$uri.' should be denied for an employee, got '.$response->status(),
            );
            $this->app['auth']->forgetGuards();
        }

        $this->assertSame('Pharmacy emp-denied', $pharmacy->fresh()->pharmacy_name);
        $this->assertSame('Amoxicillin 500mg', $medicine->fresh()->name);
        $this->assertFalse($notification->fresh()->is_read);
    }

    /** @return array{0:Pharmacy,1:Employee} */
    private function pharmacyWithEmployee(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);
        $pharmacy = $this->pharmacy($owner, $suffix);
        $employee = $this->employee($pharmacy, substr(md5($suffix), 0, 3));

        return [$pharmacy, $employee];
    }
}
