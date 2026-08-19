<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The reports answering the questions a pharmacist actually asks.
 *
 * Two of these endpoints were reachable but wired to nothing, and one of those
 * quietly ignored the period it was given. The rest ranked by units sold, which
 * says four hundred boxes of paracetamol matter more than the inhalers.
 */
class ReportAccuracyTest extends SecurityTestCase
{
    public function test_the_average_sale_is_a_basket_not_a_headcount(): void
    {
        // It used to count sales and divide by seven. What "average order"
        // means to a pharmacist is how big a typical basket is.
        [$owner, $pharmacy] = $this->buyer('ra-avg');
        $stock = $this->shelf($pharmacy, 'Paracetamol 500mg', 'Painkillers', selling: 10000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $stock, 3);
        $this->sell($pharmacy, $stock, 1);

        $this->getJson($this->url('average-sales', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('sales_count', 2)
            ->assertJsonPath('total', 40000)
            ->assertJsonPath('average_sale', 20000);
    }

    public function test_the_average_respects_the_period_it_was_asked_for(): void
    {
        // It was hardcoded to the current week whatever the client requested,
        // so a screen showing "Year" was handed this week's number.
        [$owner, $pharmacy] = $this->buyer('ra-filter');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson($this->url('average-sales', $pharmacy, 'yearly'), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('filter', 'yearly');
    }

    public function test_a_quiet_period_averages_zero_rather_than_dividing_by_it(): void
    {
        [$owner, $pharmacy] = $this->buyer('ra-quiet');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson($this->url('average-sales', $pharmacy), $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('average_sale', 0);
    }

    public function test_top_sellers_are_ranked_by_money_not_by_boxes(): void
    {
        // Four hundred boxes of paracetamol matter less than a handful of
        // inhalers, and a list ordered by units says the opposite.
        [$owner, $pharmacy] = $this->buyer('ra-top');
        $cheap = $this->shelf($pharmacy, 'Paracetamol 500mg', 'Painkillers', selling: 1000);
        $dear = $this->shelf($pharmacy, 'Salbutamol Inhaler', 'Respiratory', selling: 26000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $cheap, 20);
        $this->sell($pharmacy, $dear, 2);

        $response = $this->getJson($this->url('most-sold', $pharmacy), $this->at($pharmacy))->assertOk();

        $this->assertSame('Salbutamol Inhaler', $response->json('medicines.0.medicine'));
        $this->assertSame(52000.0, (float) $response->json('medicines.0.revenue'));
        $this->assertSame(2, $response->json('medicines.0.total_sold'));

        $this->assertSame('Paracetamol 500mg', $response->json('medicines.1.medicine'));
        $this->assertSame(20000.0, (float) $response->json('medicines.1.revenue'));
    }

    public function test_a_drug_split_across_batches_is_one_line_in_the_ranking(): void
    {
        // Batches are a filing detail of the shelf. A top-sellers list showing
        // the same drug twice is reporting the filing, not the business.
        [$owner, $pharmacy] = $this->buyer('ra-batches');
        $old = $this->shelf($pharmacy, 'Paracetamol 500mg', 'Painkillers', selling: 10000, quantity: 5, expiresInDays: 30);
        $this->shelf($pharmacy, 'Paracetamol 500mg', 'Painkillers', selling: 10000, quantity: 100, expiresInDays: 900);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        // Spills from the short-dated batch into the fresh one.
        $this->sell($pharmacy, $old, 12);

        $response = $this->getJson($this->url('most-sold', $pharmacy), $this->at($pharmacy))->assertOk();

        $this->assertCount(1, $response->json('medicines'));
        $this->assertSame(12, $response->json('medicines.0.total_sold'));
        $this->assertSame(120000.0, (float) $response->json('medicines.0.revenue'));
    }

    public function test_categories_report_what_they_earned(): void
    {
        // Which shelf earns the most is a different question from which shelf
        // moves the most boxes, and it is the useful one.
        [$owner, $pharmacy] = $this->buyer('ra-cat');
        $painkiller = $this->shelf($pharmacy, 'Paracetamol 500mg', 'Painkillers', selling: 1000);
        $inhaler = $this->shelf($pharmacy, 'Salbutamol Inhaler', 'Respiratory', selling: 26000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->sell($pharmacy, $painkiller, 20);
        $this->sell($pharmacy, $inhaler, 2);

        $response = $this->getJson($this->url('most-sold-category', $pharmacy), $this->at($pharmacy))->assertOk();

        $this->assertSame('Respiratory', $response->json('categories.0.category_medicine'));
        $this->assertSame(52000.0, (float) $response->json('categories.0.revenue'));
        $this->assertSame(20, $response->json('categories.1.total_sold'));
    }

    private function url(string $report, Pharmacy $pharmacy, string $filter = 'monthly'): string
    {
        return '/api/reports/'.$report.'?pharmacy_id='.$pharmacy->id.'&filter='.$filter;
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

    private function sell(Pharmacy $pharmacy, Medicine $stock, int $quantity): void
    {
        $this->postJson('/api/sale/create', [
            'pharmacy_id' => $pharmacy->id,
            'payment_method' => 'cash',
            'items' => [['medicine_id' => $stock->id, 'quantity' => $quantity]],
        ], $this->at($pharmacy))->assertCreated();
    }

    private function shelf(
        Pharmacy $pharmacy,
        string $name,
        string $category,
        int $selling,
        int $quantity = 500,
        int $expiresInDays = 400,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category_medicine' => $category,
            'cost_price' => $selling * 0.6,
            'selling_price' => $selling,
            'manufacturer' => 'Qasioun Labs',
            'quantity' => $quantity,
            'reorder_level' => 10,
            'expire_date' => now()->addDays($expiresInDays)->toDateString(),
        ]);
    }
}
