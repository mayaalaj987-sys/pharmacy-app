<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockWriteOff;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * A customer bringing something back.
 *
 * It happens daily and the application had no answer: a sale was written once
 * and never touched again. That instinct is right — a sale is the evidence of
 * what left the shop and what was charged — so a return is its own entry
 * pointing at the line it reverses.
 */
class SaleReturnTest extends SecurityTestCase
{
    public function test_a_refund_puts_the_stock_back_and_reverses_the_takings(): void
    {
        [$owner, $pharmacy, $stock, $sale] = $this->soldThree('rt-basic');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 2,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))
            ->assertCreated()
            ->assertJsonPath('code', 'sale_returned')
            ->assertJsonPath('refund_amount', 20000)
            ->assertJsonPath('restocked', true);

        // 497 after the sale of three, 499 with two back.
        $this->assertSame(499, $stock->fresh()->quantity);

        $this->getJson($this->url('revenue', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('gross_revenue', 30000)
            ->assertJsonPath('refunds', 20000)
            ->assertJsonPath('revenue', 10000);
    }

    public function test_a_refund_and_its_sale_cancel_out_in_profit(): void
    {
        // Both sides come off. Reversing only the revenue would charge the
        // pharmacy for goods it has back on the shelf.
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-profit');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $before = $this->getJson($this->url('profits', $pharmacy), $this->at($pharmacy))->json('profit');
        $this->assertSame(12000, $before);

        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 3,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))->assertCreated();

        $this->getJson($this->url('profits', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('revenue', 0)
            ->assertJsonPath('cost_of_goods', 0)
            ->assertJsonPath('profit', 0);
    }

    public function test_damaged_stock_is_refunded_but_never_goes_back_on_the_shelf(): void
    {
        // Selling a broken box to the next customer is the one outcome a
        // pharmacy must not allow, and quietly restocking it would be that.
        [$owner, $pharmacy, $stock, $sale] = $this->soldThree('rt-damaged');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 3,
            'reason' => SaleReturn::REASON_DAMAGED,
        ], $this->at($pharmacy))
            ->assertCreated()
            ->assertJsonPath('restocked', false);

        $this->assertSame(497, $stock->fresh()->quantity);

        // Booked as a loss instead, so the money is accounted for rather than
        // simply disappearing.
        $writeOff = StockWriteOff::sole();
        $this->assertSame(StockWriteOff::REASON_DAMAGED, $writeOff->reason);
        $this->assertSame(18000.0, $writeOff->value());
    }

    public function test_a_damaged_return_costs_the_pharmacy_what_the_goods_cost(): void
    {
        // Money back to the customer and no stock to show for it: down by the
        // cost of the boxes, not by the price they fetched.
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-damaged-profit');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 3,
            'reason' => SaleReturn::REASON_DAMAGED,
        ], $this->at($pharmacy))->assertCreated();

        $this->getJson($this->url('profits', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('write_offs', 18000)
            ->assertJsonPath('profit', -18000);
    }

    public function test_the_refund_is_what_the_customer_paid_not_todays_price(): void
    {
        [$owner, $pharmacy, $stock, $sale] = $this->soldThree('rt-price');

        // The pharmacy reprices overnight. The customer is still owed what they
        // handed over.
        $stock->update(['selling_price' => 25000]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))
            ->assertCreated()
            ->assertJsonPath('refund_amount', 10000);
    }

    public function test_nothing_can_be_returned_after_forty_eight_hours(): void
    {
        // Medicine leaves the pharmacy's control the moment it leaves the
        // counter and nobody can vouch for how it was stored.
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-late');
        Sale::where('id', $sale->id)->update(['created_at' => now()->subHours(49)]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'return_window_closed');

        $this->assertSame(0, SaleReturn::count());
    }

    public function test_a_sale_from_yesterday_is_still_inside_the_window(): void
    {
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-yesterday');
        Sale::where('id', $sale->id)->update(['created_at' => now()->subHours(30)]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))->assertCreated();
    }

    public function test_more_cannot_come_back_than_went_out(): void
    {
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-toomany');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 4,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'nothing_left_to_return')
            ->assertJsonPath('returnable_quantity', 3);

        $this->assertSame(0, SaleReturn::count());
    }

    public function test_a_line_cannot_be_returned_twice_over(): void
    {
        // Two partial refunds are legitimate; two full ones are the same boxes
        // being paid for twice.
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-twice');
        $line = SaleItem::sole()->id;

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => $line,
            'quantity' => 2,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))->assertCreated();

        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => $line,
            'quantity' => 2,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('returnable_quantity', 1);
    }

    public function test_the_counter_can_see_what_is_still_returnable(): void
    {
        // Neither how long ago the sale was nor what has already come back is
        // visible at the counter, and both decide the answer.
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-check');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_WRONG_ITEM,
        ], $this->at($pharmacy))->assertCreated();

        $this->getJson('/api/sale/'.$sale->id.'/returnable', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('returnable', true)
            ->assertJsonPath('window_hours', 48)
            ->assertJsonPath('items.0.quantity', 3)
            ->assertJsonPath('items.0.returned', 1)
            ->assertJsonPath('items.0.returnable', 2)
            ->assertJsonPath('items.0.unit_price', 10000);
    }

    public function test_an_expired_window_is_reported_before_anything_is_offered(): void
    {
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-closed');
        Sale::where('id', $sale->id)->update(['created_at' => now()->subHours(72)]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/sale/'.$sale->id.'/returnable', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('returnable', false)
            ->assertJsonPath('hours_left', 0);
    }

    public function test_the_owner_is_told_a_refund_was_given(): void
    {
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-notify');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))->assertCreated();

        $notification = Notification::where('type', 'sale_return')->sole();
        $this->assertStringContainsString('Paracetamol 500mg', $notification->message);
        $this->assertSame(Notification::AUDIENCE_OWNER, $notification->audience);
    }

    public function test_another_pharmacys_sale_cannot_be_refunded(): void
    {
        [, , , $theirSale] = $this->soldThree('rt-theirs');
        [$mine, $myPharmacy] = $this->buyer('rt-mine');

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$theirSale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 1,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($myPharmacy))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertSame(0, SaleReturn::count());
    }

    public function test_a_refund_is_cash_out_of_the_drawer(): void
    {
        [$owner, $pharmacy, , $sale] = $this->soldThree('rt-cash');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/'.$sale->id.'/return', [
            'sale_item_id' => SaleItem::sole()->id,
            'quantity' => 2,
            'reason' => SaleReturn::REASON_UNWANTED,
        ], $this->at($pharmacy))->assertCreated();

        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('money_in', 10000);
    }

    /** @return array{0: Pharmacist, 1: Pharmacy, 2: Medicine, 3: Sale} */
    private function soldThree(string $suffix): array
    {
        [$owner, $pharmacy] = $this->buyer($suffix);
        $stock = Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Paracetamol 500mg',
            'category_medicine' => 'Painkillers',
            'cost_price' => 6000,
            'selling_price' => 10000,
            'manufacturer' => 'Orontes Labs',
            'quantity' => 500,
            'reorder_level' => 10,
            'expire_date' => now()->addYear()->toDateString(),
        ]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => 3]],
        ], $this->at($pharmacy))->assertCreated();

        return [$owner, $pharmacy, $stock, Sale::sole()];
    }

    private function url(string $report, Pharmacy $pharmacy): string
    {
        return '/api/reports/'.$report.'?pharmacy_id='.$pharmacy->id.'&filter=monthly';
    }

    /** @return array{0: Pharmacist, 1: Pharmacy} */
    private function buyer(string $suffix): array
    {
        $owner = $this->pharmacist($suffix);

        return [$owner, $this->pharmacy($owner, $suffix)];
    }

    /** @return array<string, int> */
    private function at(Pharmacy $pharmacy): array
    {
        return ['X-Pharmacy-Id' => $pharmacy->id];
    }
}
