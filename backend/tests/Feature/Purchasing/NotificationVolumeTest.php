<?php

namespace Tests\Feature\Purchasing;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Keeping the bell worth looking at.
 *
 * Three days of testing produced 132 notifications, 83 of them unread. That is
 * not a busy pharmacy — it is a pharmacist who has stopped reading, which is
 * strictly worse than having no notifications at all, because the one that
 * mattered is in there somewhere.
 */
class NotificationVolumeTest extends SecurityTestCase
{
    public function test_a_pharmacist_selling_at_their_own_till_is_not_told_about_it(): void
    {
        // They were standing there. A hundred of these a day is how the bell
        // became wallpaper.
        [$owner, $pharmacy] = $this->shop('vol-own');
        $stock = $this->shelf($pharmacy);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock);

        $this->assertSame(0, Notification::where('type', 'sale')->count());
    }

    public function test_a_sale_by_staff_is_worth_telling_the_owner(): void
    {
        // What an owner cannot see for themselves is what was sold while they
        // were out, and by whom.
        [$owner, $pharmacy] = $this->shop('vol-staff');
        $stock = $this->shelf($pharmacy);
        $employee = $this->employee($pharmacy, 'vol-staff');

        Sanctum::actingAs($employee, ['*'], 'employee');
        $this->sell($pharmacy, $stock);

        $notification = Notification::where('type', 'sale')->sole();
        $this->assertStringContainsString($employee->name, $notification->title);
        $this->assertSame(Notification::AUDIENCE_OWNER, $notification->audience);
        $this->assertTrue($owner->exists);
    }

    public function test_the_three_things_that_happen_to_an_order_read_differently(): void
    {
        // They shared one type, and the client replaces non-English text with a
        // single generic line per type — so placed, arrived and cancelled all
        // read "a purchase order status has changed".
        [$owner, $pharmacy] = $this->shop('vol-order');
        $received = $this->order($pharmacy, 'a');
        $cancelled = $this->order($pharmacy, 'b');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$received->id.'/receive', [], $this->at($pharmacy))->assertOk();
        $this->postJson('/api/orders/'.$cancelled->id.'/cancel', [], $this->at($pharmacy))->assertOk();

        $this->assertSame(1, Notification::where('type', 'order_received')->count());
        $this->assertSame(1, Notification::where('type', 'order_cancelled')->count());
    }

    public function test_a_notification_says_what_it_is_about(): void
    {
        // Every one of these used to be a dead end: tapping it marked it read
        // and left the pharmacist to go and find the thing themselves.
        [$owner, $pharmacy] = $this->shop('vol-ref');
        $order = $this->order($pharmacy, 'ref');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $this->assertSame(
            $order->id,
            Notification::where('type', 'order_received')->sole()->reference_id,
        );
    }

    public function test_a_shortage_is_reported_again_once_the_warning_is_stale(): void
    {
        // It used to check the whole history, so a drug reported low once was
        // never reported again — restock it, sell it out, and silence.
        [$owner, $pharmacy] = $this->shop('vol-again');
        $stock = $this->shelf($pharmacy, quantity: 12, reorder: 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock, 4);
        $this->assertSame(1, Notification::where('type', 'low_stock')->count());

        // Restocked, drained again, and a fortnight has passed.
        Notification::query()->update(['created_at' => now()->subDays(14)]);
        $stock->fresh()->update(['quantity' => 12]);

        $this->sell($pharmacy, $stock->fresh(), 4);

        $this->assertSame(2, Notification::where('type', 'low_stock')->count());
    }

    public function test_the_same_shortage_is_not_repeated_the_same_week(): void
    {
        [$owner, $pharmacy] = $this->shop('vol-quiet');
        $stock = $this->shelf($pharmacy, quantity: 12, reorder: 10);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock, 1);
        $this->sell($pharmacy, $stock->fresh(), 1);
        $this->sell($pharmacy, $stock->fresh(), 1);

        $this->assertSame(1, Notification::where('type', 'low_stock')->count());
    }

    public function test_a_message_to_one_person_is_marked_as_theirs(): void
    {
        // It used to fall through to the owner default, so every employee
        // message read as the owner's in the table while being routed by
        // employee_id. The column said one thing and the code did another.
        [, $pharmacy] = $this->shop('vol-audience');
        $employee = $this->employee($pharmacy, 'vol-audience');

        Notification::notifyEmployee($employee->id, 'A pharmacy read your CV', 'Barada did.', 'cv_viewed');

        $notification = Notification::where('type', 'cv_viewed')->sole();
        $this->assertSame(Notification::AUDIENCE_STAFF, $notification->audience);
        $this->assertNull($notification->pharmacy_id);
    }

    public function test_every_notification_carries_the_moment_it_happened(): void
    {
        // The same column used to hold a date from one helper and a datetime
        // from every direct create, so ordering and display disagreed depending
        // on who wrote the row.
        [$owner, $pharmacy] = $this->shop('vol-date');
        $order = $this->order($pharmacy, 'date');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/orders/'.$order->id.'/receive', [], $this->at($pharmacy))->assertOk();

        $stored = Notification::where('type', 'order_received')->sole()->getRawOriginal('date');
        $this->assertNotSame(now()->toDateString(), $stored, 'date lost its time of day');
    }

    public function test_read_notifications_are_pruned_and_unread_ones_are_not(): void
    {
        // An unread notification is still asking for something however old it
        // is. The one thing worse than a cluttered bell is one that quietly
        // drops the message that mattered.
        [, $pharmacy] = $this->shop('vol-prune');

        $old = Notification::notify($pharmacy->id, 'Old', 'Read and stale.', 'sale');
        $oldUnread = Notification::notify($pharmacy->id, 'Old', 'Unread and stale.', 'sale');
        $recent = Notification::notify($pharmacy->id, 'Recent', 'Read but recent.', 'sale');

        $old->update(['is_read' => true]);
        $recent->update(['is_read' => true]);
        Notification::whereIn('id', [$old->id, $oldUnread->id])
            ->update(['created_at' => now()->subDays(40)]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertNull($old->fresh());
        $this->assertNotNull($oldUnread->fresh());
        $this->assertNotNull($recent->fresh());
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function shop(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    /** @return array<string, int> */
    private function at(Pharmacy $pharmacy): array
    {
        return ['X-Pharmacy-Id' => $pharmacy->id];
    }

    private function sell(Pharmacy $pharmacy, Medicine $stock, int $quantity = 1): void
    {
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => $quantity]],
        ], $this->at($pharmacy))->assertCreated();
    }

    private function shelf(Pharmacy $pharmacy, int $quantity = 500, int $reorder = 10): Medicine
    {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Paracetamol 500mg',
            'category_medicine' => 'Painkillers',
            'cost_price' => 6000,
            'selling_price' => 10000,
            'manufacturer' => 'Orontes Labs',
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'expire_date' => now()->addYear()->toDateString(),
        ]);
    }

    private function order(Pharmacy $pharmacy, string $suffix): Order
    {
        $supplier = Supplier::create([
            'name' => 'Barada '.$suffix,
            'phone' => '0930111222',
            'email' => $suffix.'-'.$pharmacy->id.'@example.demo',
            'address' => 'Damascus',
        ]);

        $offer = Medicine::create([
            'pharmacy_id' => null,
            'supplier_id' => $supplier->id,
            'name' => 'Amoxicillin 500mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => 8000,
            'selling_price' => 12500,
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => now()->addYear()->toDateString(),
        ]);

        $order = Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => 80000,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'medicine_id' => $offer->id,
            'quantity' => 10,
            'price' => 8000,
        ]);

        return $order;
    }
}
