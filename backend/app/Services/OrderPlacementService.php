<?php

namespace App\Services;

use App\Exceptions\SupplierStockException;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;

/**
 * The only place an order is written.
 *
 * Two ways to buy — one drug at a time, or a whole cart at checkout — but one
 * writer, because placing an order also reserves units against a catalogue
 * shared by every pharmacy on the platform. A second copy of that reservation
 * would eventually disagree with this one about what has been taken.
 *
 * Does not open its own transaction: a cart checkout places several orders that
 * must succeed or fail together, so the boundary belongs to the caller. Nor
 * does it notify — a single order and a five-supplier checkout have nothing
 * useful to say in the same sentence.
 */
class OrderPlacementService
{
    /**
     * Places one supplier's order and reserves the units it needs.
     *
     * @param  list<array{medicine_id: int|string, quantity: int|string}>  $items
     *
     * @throws SupplierStockException when the supplier is short
     */
    public function place(Pharmacy $pharmacy, int $supplierId, array $items, string $paymentMethod): Order
    {
        $priced = [];
        $totalPrice = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];

            // Orders may reference only the global supplier catalogue, never
            // another tenant's stock row. Locked because the catalogue is
            // shared: two pharmacies ordering the last units at once must not
            // both succeed.
            $medicine = Medicine::whereNull('pharmacy_id')
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->find($item['medicine_id']);

            if (! $medicine) {
                throw new \InvalidArgumentException('Medicine '.$item['medicine_id'].' is not offered by supplier '.$supplierId.'.');
            }

            if ($medicine->quantity < $quantity) {
                throw new SupplierStockException($medicine, $quantity);
            }

            $priced[] = [$medicine, $quantity];
            $totalPrice += (float) $medicine->cost_price * $quantity;
        }

        $order = Order::create([
            'supplier_id' => $supplierId,
            'pharmacy_id' => $pharmacy->id,
            'date' => now()->toDateString(),
            'total_price' => $totalPrice,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
        ]);

        foreach ($priced as [$medicine, $quantity]) {
            OrderItem::create([
                'order_id' => $order->id,
                'medicine_id' => $medicine->id,
                'quantity' => $quantity,
                // The price actually agreed, frozen here. The catalogue moves;
                // what this order costs must not move with it, and receiving
                // reads this back as the cost of the stock that arrives.
                'price' => $medicine->cost_price,
            ]);

            // Reserve the units against the supplier catalogue so the advertised
            // availability stays truthful. Cancelling the order releases them.
            $medicine->decrement('quantity', $quantity);
        }

        return $order;
    }
}
