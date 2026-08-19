<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Money in and money out, which profit deliberately does not measure.
 *
 * Buying stock never touches profit, and that is right: cash turned into
 * inventory is the same value in a different form, not a cost. Which is exactly
 * how a pharmacy trades profitably and still cannot pay anyone — and until this
 * existed, no screen in the application would have shown that happening.
 */
class CashFlowTest extends SecurityTestCase
{
    public function test_buying_stock_empties_the_till_without_touching_profit(): void
    {
        // The whole reason the two figures have to sit side by side.
        [$owner, $pharmacy] = $this->buyer('cf-both');
        $this->purchase($pharmacy, 2_000_000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson($this->url('profits', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('profit', 0);

        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('purchases', 2000000)
            ->assertJsonPath('net', -2000000);
    }

    public function test_takings_are_split_by_how_they_were_paid(): void
    {
        // Not the same money: cash is in the drawer tonight, a card settles
        // later and an insurance claim later still.
        [$owner, $pharmacy] = $this->buyer('cf-methods');
        $stock = $this->shelf($pharmacy);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock, 2, 'cash');
        $this->sell($pharmacy, $stock, 1, 'card');

        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('money_in', 30000)
            ->assertJsonPath('money_in_by_method.cash', 20000)
            ->assertJsonPath('money_in_by_method.card', 10000)
            ->assertJsonPath('money_in_by_method.insurance', 0);
    }

    public function test_a_cancelled_order_is_not_money_out(): void
    {
        // It was never paid for. Counting it would show cash gone that is
        // sitting in the drawer.
        [$owner, $pharmacy] = $this->buyer('cf-cancelled');
        $this->purchase($pharmacy, 500_000)->update(['status' => 'cancelled']);
        $this->purchase($pharmacy, 300_000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('purchases', 300000);
    }

    public function test_an_order_still_awaiting_delivery_has_already_been_paid(): void
    {
        // Counted from the day it was placed, which is when a Syrian wholesaler
        // is paid. Waiting for it to be marked received would leave money
        // already gone sitting in a report as though it were still there.
        [$owner, $pharmacy] = $this->buyer('cf-pending');
        $this->purchase($pharmacy, 750_000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('purchases', 750000);
    }

    public function test_wages_count_against_the_till_as_well(): void
    {
        [$owner, $pharmacy] = $this->buyer('cf-wages');
        $this->employee($pharmacy, 'cf-wages')->update(['salary' => 400000]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $response = $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))->assertOk();

        $this->assertGreaterThan(0, $response->json('salaries'));
        $this->assertSame(
            $response->json('salaries') + $response->json('purchases'),
            $response->json('money_out'),
        );
    }

    public function test_a_quiet_period_reports_zero_rather_than_nothing(): void
    {
        [$owner, $pharmacy] = $this->buyer('cf-quiet');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson($this->url('cash-flow', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('money_in', 0)
            ->assertJsonPath('money_out', 0)
            ->assertJsonPath('net', 0);
    }

    public function test_the_payment_breakdown_reports_shares_that_add_up(): void
    {
        // Worked out here so a donut does not compute its own and disagree with
        // the figure printed beside it.
        [$owner, $pharmacy] = $this->buyer('cf-share');
        $stock = $this->shelf($pharmacy);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock, 3, 'cash');
        $this->sell($pharmacy, $stock, 1, 'card');

        $response = $this->getJson($this->url('payment-methods', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('total', 40000);

        $shares = collect($response->json('methods'))->pluck('share', 'payment_method');
        $this->assertSame(75.0, (float) $shares['cash']);
        $this->assertSame(25.0, (float) $shares['card']);

        $counts = collect($response->json('methods'))->pluck('sales', 'payment_method');
        $this->assertSame(1, $counts['cash']);
        $this->assertSame(1, $counts['card']);
    }

    public function test_one_pharmacys_till_is_never_counted_in_anothers(): void
    {
        [$mine, $myPharmacy] = $this->buyer('cf-mine');
        [, $theirPharmacy] = $this->buyer('cf-theirs');
        $this->purchase($theirPharmacy, 999_000);
        $this->purchase($myPharmacy, 100_000);

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->getJson($this->url('cash-flow', $myPharmacy), $this->at($myPharmacy))
            ->assertOk()
            ->assertJsonPath('purchases', 100000);
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

    private function sell(Pharmacy $pharmacy, Medicine $stock, int $quantity, string $method): void
    {
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => $method,
            // Omitted rather than sent as null: `digits:10` runs on a present
            // null and fails, which is a quirk of the existing rule set.
            ...($method === 'card' ? ['card_number' => '1234567890'] : []),
            'items' => [['medicine_id' => $stock->id, 'quantity' => $quantity]],
        ], $this->at($pharmacy))->assertCreated();
    }

    private function purchase(Pharmacy $pharmacy, int $total): Order
    {
        $supplier = Supplier::create([
            'name' => 'Barada '.$pharmacy->id.'-'.$total,
            'phone' => '0930111222',
            'email' => md5($pharmacy->id.$total).'@example.demo',
            'address' => 'Damascus',
        ]);

        return Order::create([
            'supplier_id' => $supplier->id,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => $total,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);
    }

    private function shelf(Pharmacy $pharmacy): Medicine
    {
        return Medicine::create([
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
    }
}
