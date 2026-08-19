<?php

namespace Tests\Feature\Purchasing;

use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\StockWriteOff;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Booking stock that left the shelf without being sold.
 *
 * Inventory could only go up by receiving and down by selling. Everything else
 * that happens to real stock had nowhere to go but a pharmacist editing the
 * quantity, which records neither the event nor its cost.
 *
 * The consequence was that expired stock cost the pharmacy nothing on paper. It
 * did not reduce profit when it was bought — correct, it became inventory — and
 * it never reduced profit afterwards either, because stock that is never sold
 * never enters cost of goods. The money simply left the books.
 */
class StockWriteOffTest extends SecurityTestCase
{
    public function test_writing_off_removes_the_stock_and_records_the_loss(): void
    {
        [$owner, $pharmacy] = $this->buyer('wo-book');
        $batch = $this->shelf($pharmacy, quantity: 41, cost: 12000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 41,
            'reason' => StockWriteOff::REASON_EXPIRED,
        ], $this->at($pharmacy))
            ->assertCreated()
            ->assertJsonPath('code', 'stock_written_off')
            ->assertJsonPath('write_off.value', 492000)
            ->assertJsonPath('remaining_quantity', 0);

        $this->assertSame(0, $batch->fresh()->quantity);
        $this->assertSame(492000.0, StockWriteOff::sole()->value());
    }

    public function test_the_loss_finally_shows_up_in_profit(): void
    {
        // The whole point. Before this, stock bought and thrown away reduced
        // profit at no point in its life and stayed in the valuation as an asset.
        [$owner, $pharmacy] = $this->buyer('wo-profit');
        $batch = $this->shelf($pharmacy, quantity: 41, cost: 12000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/reports/profits?pharmacy_id='.$pharmacy->id.'&filter=monthly', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('write_offs', 0)
            ->assertJsonPath('profit', 0);

        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 41,
            'reason' => StockWriteOff::REASON_EXPIRED,
        ], $this->at($pharmacy))->assertCreated();

        $this->getJson('/api/reports/profits?pharmacy_id='.$pharmacy->id.'&filter=monthly', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('write_offs', 492000)
            ->assertJsonPath('profit', -492000);
    }

    public function test_stock_sent_back_to_the_supplier_is_not_a_loss(): void
    {
        // It is replaced or refunded. Counting it would show a pharmacy losing
        // money for returning something it never wanted.
        [$owner, $pharmacy] = $this->buyer('wo-returned');
        $batch = $this->shelf($pharmacy, quantity: 10, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 10,
            'reason' => StockWriteOff::REASON_RETURNED,
        ], $this->at($pharmacy))->assertCreated();

        // Off the shelf, but not charged against the pharmacy.
        $this->assertSame(0, $batch->fresh()->quantity);
        $this->getJson('/api/reports/profits?pharmacy_id='.$pharmacy->id.'&filter=monthly', $this->at($pharmacy))
            ->assertJsonPath('write_offs', 0);
    }

    public function test_the_cost_is_frozen_at_the_moment_it_is_booked(): void
    {
        // Receiving blends a drug's recorded cost. A loss booked in March must
        // still read as it did in March.
        [$owner, $pharmacy] = $this->buyer('wo-frozen');
        $batch = $this->shelf($pharmacy, quantity: 10, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 4,
            'reason' => StockWriteOff::REASON_DAMAGED,
        ], $this->at($pharmacy))->assertCreated();

        $batch->update(['cost_price' => 99000]);

        $this->assertSame(5000.0, (float) StockWriteOff::sole()->unit_cost);
        $this->assertSame(20000.0, StockWriteOff::sole()->value());
    }

    public function test_more_cannot_be_written_off_than_is_on_the_shelf(): void
    {
        [$owner, $pharmacy] = $this->buyer('wo-toomany');
        $batch = $this->shelf($pharmacy, quantity: 5, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 6,
            'reason' => StockWriteOff::REASON_LOST,
        ], $this->at($pharmacy))
            ->assertStatus(409)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('medicine.available_quantity', 5);

        $this->assertSame(5, $batch->fresh()->quantity);
        $this->assertSame(0, StockWriteOff::count());
    }

    public function test_only_the_four_reasons_are_accepted(): void
    {
        // A free-text reason is a field nobody can report on.
        [$owner, $pharmacy] = $this->buyer('wo-reason');
        $batch = $this->shelf($pharmacy, quantity: 10, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 1,
            'reason' => 'because',
        ], $this->at($pharmacy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_one_batch_can_be_written_off_without_touching_the_other(): void
    {
        // It is a specific pile of boxes that expired, and the cost recorded has
        // to be the cost of those boxes.
        [$owner, $pharmacy] = $this->buyer('wo-batch');
        $old = $this->shelf($pharmacy, quantity: 5, cost: 5000, expiresInDays: -10);
        $fresh = $this->shelf($pharmacy, quantity: 200, cost: 9000, expiresInDays: 900);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$old->id.'/write-off', [
            'quantity' => 5,
            'reason' => StockWriteOff::REASON_EXPIRED,
        ], $this->at($pharmacy))->assertCreated();

        $this->assertSame(0, $old->fresh()->quantity);
        $this->assertSame(200, $fresh->fresh()->quantity);
        // The old batch's cost, not the fresh one's.
        $this->assertSame(25000.0, StockWriteOff::sole()->value());
    }

    public function test_the_owner_is_told_when_stock_is_written_off(): void
    {
        // Money leaving the pharmacy, whoever booked it.
        [$owner, $pharmacy] = $this->buyer('wo-notify');
        $batch = $this->shelf($pharmacy, quantity: 10, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
            'quantity' => 3,
            'reason' => StockWriteOff::REASON_DAMAGED,
        ], $this->at($pharmacy))->assertCreated();

        $notification = Notification::where('type', 'write_off')->sole();
        $this->assertStringContainsString('Azithromycin 250mg', $notification->message);
        $this->assertStringContainsString('damaged', $notification->message);
        $this->assertSame(Notification::AUDIENCE_OWNER, $notification->audience);
    }

    public function test_another_pharmacys_stock_cannot_be_written_off(): void
    {
        [$mine, $myPharmacy] = $this->buyer('wo-mine');
        [, $theirPharmacy] = $this->buyer('wo-theirs');
        $theirs = $this->shelf($theirPharmacy, quantity: 50, cost: 5000);

        Sanctum::actingAs($mine, ['*'], 'pharmacist');
        $this->postJson('/api/medicines/'.$theirs->id.'/write-off', [
            'quantity' => 50,
            'reason' => StockWriteOff::REASON_LOST,
        ], $this->at($myPharmacy))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertSame(50, $theirs->fresh()->quantity);
        $this->assertSame(0, StockWriteOff::count());
    }

    public function test_the_history_totals_what_has_been_lost(): void
    {
        [$owner, $pharmacy] = $this->buyer('wo-history');
        $batch = $this->shelf($pharmacy, quantity: 20, cost: 5000);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        foreach ([[3, StockWriteOff::REASON_EXPIRED], [2, StockWriteOff::REASON_DAMAGED], [5, StockWriteOff::REASON_RETURNED]] as [$quantity, $reason]) {
            $this->postJson('/api/medicines/'.$batch->id.'/write-off', [
                'quantity' => $quantity,
                'reason' => $reason,
            ], $this->at($pharmacy))->assertCreated();
        }

        $this->getJson('/api/medicines/write-offs', $this->at($pharmacy))
            ->assertOk()
            ->assertJsonCount(3, 'write_offs')
            // 5 lost boxes at 5,000. The five returned to the supplier are not
            // a loss and are left out of the figure.
            ->assertJsonPath('total_value', 25000);
    }

    public function test_the_inventory_valuation_separates_stock_that_cannot_be_sold(): void
    {
        // Calling it inventory value without qualification tells the pharmacist
        // they hold money they do not.
        [$owner, $pharmacy] = $this->buyer('wo-value');
        $this->shelf($pharmacy, quantity: 41, cost: 12000, expiresInDays: -600);
        $this->shelf($pharmacy, quantity: 10, cost: 8000, expiresInDays: 40);
        $this->shelf($pharmacy, quantity: 100, cost: 3000, expiresInDays: 900);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/reports/inventory-value?pharmacy_id='.$pharmacy->id, $this->at($pharmacy))
            ->assertOk()
            ->assertJsonPath('total_cost_value', 872000)
            ->assertJsonPath('expired_cost_value', 492000)
            ->assertJsonPath('expiring_cost_value', 80000);
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

    private function shelf(
        Pharmacy $pharmacy,
        int $quantity,
        int $cost,
        int $expiresInDays = 400,
    ): Medicine {
        return Medicine::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Azithromycin 250mg',
            'category_medicine' => 'Antibiotics',
            'cost_price' => $cost,
            'selling_price' => $cost * 1.5,
            'manufacturer' => 'Ugarit Pharma',
            'quantity' => $quantity,
            'reorder_level' => 10,
            'expire_date' => now()->addDays($expiresInDays)->toDateString(),
        ]);
    }
}
