<?php

namespace Tests\Feature\Integration;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * End-to-end integration coverage for the operational chain a demo walks
 * through: order from a supplier catalogue, receive it into stock, sell it at
 * the point of sale, then read the numbers back out of the reports.
 *
 * Every step goes through the real HTTP routes so the tenant guards, the
 * `X-Pharmacy-Id` context and the stock arithmetic are all exercised together.
 */
class PharmacyOperationsFlowTest extends SecurityTestCase
{
    public function test_purchase_receive_sell_and_report_run_end_to_end(): void
    {
        $owner = $this->pharmacist('flow');
        $pharmacy = $this->pharmacy($owner, 'flow');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id, cost: 8000, sell: 12500, reorder: 20);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        // 1. The pharmacy has no stock of its own yet.
        $this->getJson('/api/medicines?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonCount(0, 'medicines');

        // 2. Place a purchase order against the supplier catalogue.
        $order = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 100]],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total_price', 800000);

        $orderId = $order->json('order_id');

        // The catalogue row is untouched: ordering does not move global stock.
        $this->assertSame(500, $catalogue->fresh()->quantity);

        // 3. Receiving the order creates the pharmacy's own stock row.
        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();

        $this->assertSame('received', Order::find($orderId)->status);

        $stock = Medicine::where('pharmacy_id', $pharmacy->id)->sole();
        $this->assertSame(100, $stock->quantity);
        $this->assertSame($catalogue->name, $stock->name);
        $this->assertSame('12500.00', $stock->selling_price);

        // 4. Sell 30 units at the point of sale.
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'customer_name' => 'Walk In Customer',
            'items' => [['medicine_id' => $stock->id, 'quantity' => 30]],
        ], $headers)->assertCreated();

        $this->assertSame(70, $stock->fresh()->quantity);

        // 5. The reports read the sale back.
        $this->getJson('/api/reports/dashboard?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('today_sales_count', 1)
            ->assertJsonPath('today_revenue', 375000)
            ->assertJsonPath('low_stock_count', 0);

        $this->getJson('/api/sale/all?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('total_sales', 1);

        // 6. Selling down to the reorder level makes the medicine show as low stock.
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => 55]],
        ], $headers)->assertCreated();

        $this->assertSame(15, $stock->fresh()->quantity);

        $this->getJson('/api/medicines/low-stock?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('low_stock_count', 1)
            ->assertJsonPath('low_stock_medicines.0.id', $stock->id);

        $this->getJson('/api/reports/dashboard?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('low_stock_count', 1);
    }

    public function test_a_second_receipt_of_the_same_medicine_tops_up_the_existing_row(): void
    {
        $owner = $this->pharmacist('topup');
        $pharmacy = $this->pharmacy($owner, 'topup');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        foreach ([40, 25] as $quantity) {
            $orderId = $this->postJson('/api/orders', [
                'supplier_id' => $supplier->id,
                'pharmacy_id' => $pharmacy->id,
                'payment_method' => 'cash',
                'items' => [['medicine_id' => $catalogue->id, 'quantity' => $quantity]],
            ], $headers)->assertCreated()->json('order_id');

            $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();
        }

        // One stock row, not two.
        $this->assertSame(1, Medicine::where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(65, Medicine::where('pharmacy_id', $pharmacy->id)->sole()->quantity);
    }

    public function test_receiving_the_same_order_twice_does_not_double_the_stock(): void
    {
        $owner = $this->pharmacist('double');
        $pharmacy = $this->pharmacy($owner, 'double');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 50]],
        ], $headers)->assertCreated()->json('order_id');

        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();
        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertStatus(400);

        $this->assertSame(50, Medicine::where('pharmacy_id', $pharmacy->id)->sole()->quantity);
    }

    public function test_a_sale_cannot_exceed_the_stock_on_hand(): void
    {
        $owner = $this->pharmacist('oversell');
        $pharmacy = $this->pharmacy($owner, 'oversell');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 10]],
        ], $headers)->assertCreated()->json('order_id');

        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();
        $stock = Medicine::where('pharmacy_id', $pharmacy->id)->sole();

        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => 11]],
        ], $headers)->assertStatus(400);

        // The rolled-back sale left the stock and the sales report untouched.
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->getJson('/api/reports/dashboard?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('today_sales_count', 0);
    }

    public function test_a_pharmacy_cannot_order_from_another_tenants_stock_row(): void
    {
        $owner = $this->pharmacist('cross-order');
        $pharmacy = $this->pharmacy($owner, 'cross-order');
        $victim = $this->pharmacist('cross-order-victim');
        $victimPharmacy = $this->pharmacy($victim, 'cross-order-victim');
        $supplier = $this->supplier();

        // A stock row owned by another pharmacy, not a catalogue row.
        $foreign = $this->catalogueMedicine($supplier->id);
        $foreign->update(['pharmacy_id' => $victimPharmacy->id]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $foreign->id, 'quantity' => 5]],
        ], ['X-Pharmacy-Id' => $pharmacy->id])->assertStatus(400);

        $this->assertSame(0, Order::where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(500, $foreign->fresh()->quantity);
    }

    public function test_an_employee_sale_is_attributed_to_that_employee_in_their_own_report(): void
    {
        $owner = $this->pharmacist('attrib');
        $pharmacy = $this->pharmacy($owner, 'attrib');
        $employee = $this->employee($pharmacy, '801');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id, sell: 10000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 20]],
        ], $headers)->assertCreated()->json('order_id');

        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();
        $stock = Medicine::where('pharmacy_id', $pharmacy->id)->sole();

        // The employee sells; the actor is taken from the token, never the body.
        // `auth:pharmacist,employee` tries the pharmacist guard first, so the
        // owner set above has to be forgotten or it would still win.
        $this->actAsEmployee($employee);
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'employee_id' => $employee->id,
            'items' => [['medicine_id' => $stock->id, 'quantity' => 4]],
        ], $headers)->assertCreated();

        $this->getJson('/api/sale/my-sales?employee_id='.$employee->id, $headers)
            ->assertOk()
            ->assertJsonPath('total_sales', 1)
            ->assertJsonPath('total_price', 40000);

        $this->assertSame(16, $stock->fresh()->quantity);
    }

    public function test_task_assignment_and_completion_each_raise_a_notification(): void
    {
        $owner = $this->pharmacist('task-notify');
        $pharmacy = $this->pharmacy($owner, 'task-notify');
        $employee = $this->employee($pharmacy, '802');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/tasks', [
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Restock the antibiotics shelf',
            'description' => 'Move the received Amoxicillin stock to shelf A.',
        ], $headers)->assertCreated();

        $task = Task::where('pharmacy_id', $pharmacy->id)->sole();
        $this->assertSame('pending', $task->status);
        $this->assertSame(1, Notification::where('pharmacy_id', $pharmacy->id)->where('type', 'task')->count());

        $this->actAsEmployee($employee);
        $this->postJson('/api/tasks/'.$task->id.'/done', [], $headers)->assertOk();

        $this->assertSame('done', $task->fresh()->status);

        // Assignment and completion are two distinct notifications.
        $notifications = Notification::where('pharmacy_id', $pharmacy->id)
            ->where('type', 'task')
            ->get();
        $this->assertCount(2, $notifications);

        // Every notification carries a type the Flutter client can map to English.
        foreach ($notifications as $notification) {
            $this->assertContains($notification->type, [
                'task', 'order', 'sale', 'employee', 'employee_approved',
                'pharmacy_approved', 'pharmacy_rejected', 'pharmacist',
                'low_stock', 'out_of_stock', 'medicine',
            ]);
        }

        // Completing it twice is rejected and adds nothing.
        $this->postJson('/api/tasks/'.$task->id.'/done', [], $headers)->assertStatus(400);
        $this->assertSame(2, Notification::where('pharmacy_id', $pharmacy->id)->where('type', 'task')->count());
    }

    public function test_the_order_lifecycle_raises_notifications_the_owner_can_read(): void
    {
        $owner = $this->pharmacist('order-notify');
        $pharmacy = $this->pharmacy($owner, 'order-notify');
        $supplier = $this->supplier();
        $catalogue = $this->catalogueMedicine($supplier->id);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $headers = ['X-Pharmacy-Id' => $pharmacy->id];

        $orderId = $this->postJson('/api/orders', [
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $catalogue->id, 'quantity' => 5]],
        ], $headers)->assertCreated()->json('order_id');

        $this->postJson('/api/orders/'.$orderId.'/receive', [], $headers)->assertOk();

        $this->getJson('/api/notifications?pharmacy_id='.$pharmacy->id, $headers)
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications');
    }

    /**
     * Switch the acting identity from the owner to one of their employees.
     *
     * `Sanctum::actingAs` only sets the requested guard; the pharmacist guard
     * resolved earlier in the test stays populated and would be picked first by
     * `auth:pharmacist,employee`, so it is cleared explicitly.
     */
    private function actAsEmployee(Employee $employee): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee, ['*'], 'employee');
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Damascus Medical Supplies (Demo)',
            'phone' => '0930111222',
            'email' => 'contact@damascus-medical.demo',
            'address' => 'Mazzeh, Damascus',
        ]);
    }

    private function catalogueMedicine(
        int $supplierId,
        float $cost = 8000,
        float $sell = 12500,
        int $reorder = 20,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplierId,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $sell,
            'manufacturer' => 'Tamico',
            'quantity' => 500,
            'reorder_level' => $reorder,
            'expire_date' => now()->addMonths(18)->toDateString(),
            'qr_code' => '1111',
        ]);
    }
}
