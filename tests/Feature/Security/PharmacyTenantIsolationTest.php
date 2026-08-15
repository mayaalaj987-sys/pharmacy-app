<?php

namespace Tests\Feature\Security;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

class PharmacyTenantIsolationTest extends SecurityTestCase
{
    public function test_pharmacist_cannot_list_another_pharmacists_medicines(): void
    {
        $owner = $this->pharmacist('owner');
        $otherOwner = $this->pharmacist('other');
        $this->pharmacy($owner, 'owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'other');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/medicines?pharmacy_id='.$otherPharmacy->id)->assertForbidden();
    }

    public function test_employee_cannot_override_their_pharmacy_context(): void
    {
        $owner = $this->pharmacist('employee-owner');
        $otherOwner = $this->pharmacist('employee-other');
        $pharmacy = $this->pharmacy($owner, 'employee-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'employee-other');
        $employee = $this->employee($pharmacy, '101');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/medicines?pharmacy_id='.$otherPharmacy->id)->assertForbidden();
    }

    public function test_user_cannot_mutate_another_pharmacys_notification(): void
    {
        $owner = $this->pharmacist('notification-owner');
        $otherOwner = $this->pharmacist('notification-other');
        $this->pharmacy($owner, 'notification-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'notification-other');
        $notification = Notification::create([
            'pharmacy_id' => $otherPharmacy->id,
            'title' => 'Private',
            'message' => 'Private notification',
            'type' => 'test',
            'is_read' => false,
            'date' => now(),
        ]);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertForbidden();
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_pharmacist_cannot_edit_another_pharmacys_medicine(): void
    {
        $owner = $this->pharmacist('edit-owner');
        $otherOwner = $this->pharmacist('edit-other');
        $this->pharmacy($owner, 'edit-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'edit-other');
        $medicine = Medicine::create([
            'pharmacy_id' => $otherPharmacy->id,
            'name' => 'Private Medicine',
            'category_medicine' => 'Vitamins',
            'cost_price' => 10,
            'selling_price' => 20,
            'quantity' => 5,
        ]);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/medicines/edit/'.$medicine->id, ['name' => 'Changed'])->assertForbidden();
        $this->assertSame('Private Medicine', $medicine->fresh()->name);
    }

    public function test_pharmacist_cannot_access_another_pharmacys_reports_or_employees(): void
    {
        $owner = $this->pharmacist('report-owner');
        $otherOwner = $this->pharmacist('report-other');
        $this->pharmacy($owner, 'report-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'report-other');
        $this->employee($otherPharmacy, '301');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/reports/dashboard?pharmacy_id='.$otherPharmacy->id)->assertForbidden();
        $this->getJson('/api/employees/'.$otherPharmacy->id)->assertForbidden();
    }

    public function test_pharmacist_cannot_access_another_pharmacys_order(): void
    {
        $owner = $this->pharmacist('order-owner');
        $otherOwner = $this->pharmacist('order-other');
        $this->pharmacy($owner, 'order-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'order-other');
        $supplier = Supplier::create(['name' => 'Supplier']);
        $order = Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $otherPharmacy->id,
            'payment_method' => 'cash',
            'status' => 'pending',
            'total_price' => 10,
            'date' => now(),
        ]);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/orders/'.$order->id)->assertForbidden();
        $this->postJson('/api/orders/'.$order->id.'/cancel')->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_pharmacist_cannot_delete_another_pharmacys_task(): void
    {
        $owner = $this->pharmacist('task-owner');
        $otherOwner = $this->pharmacist('task-other');
        $this->pharmacy($owner, 'task-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'task-other');
        $employee = $this->employee($otherPharmacy, '401');
        $task = Task::create([
            'pharmacy_id' => $otherPharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Private task',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->deleteJson('/api/tasks/'.$task->id)->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
