<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockWriteOff;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Taking part of a sale back.
 *
 * A daily event in a pharmacy that the application had no answer for: a sale
 * was written once and never touched again. That instinct is right — a sale
 * records what left the shop and what was charged, and editing one to make a
 * refund fit destroys the evidence — so a return is its own entry pointing at
 * the line it reverses, and the two are read together.
 */
class SaleReturnController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    /**
     * What can still be brought back from this sale, and what already was.
     *
     * Read before offering the customer anything, because the answer depends on
     * two things the counter cannot see: how long ago the sale was, and whether
     * part of it has already come back.
     */
    public function show(Request $request, int $sale): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        $record = Sale::where('pharmacy_id', $pharmacy->id)
            ->with(['items.medicine'])
            ->find($sale);

        if (! $record) {
            return $this->noSuchSale();
        }

        $returnedByLine = SaleReturn::where('sale_id', $record->id)
            ->selectRaw('sale_item_id, SUM(quantity) as returned')
            ->groupBy('sale_item_id')
            ->pluck('returned', 'sale_item_id');

        $hoursSince = $record->created_at->diffInHours(now());
        $open = $hoursSince < SaleReturn::WINDOW_HOURS;

        return response()->json([
            'sale' => [
                'id' => $record->id,
                'total_price' => (float) $record->total_price,
                'payment_method' => $record->payment_method,
                'customer_name' => $record->customer_name,
                'sold_at' => $record->created_at->toISOString(),
            ],
            'returnable' => $open,
            'hours_left' => $open ? SaleReturn::WINDOW_HOURS - (int) $hoursSince : 0,
            'window_hours' => SaleReturn::WINDOW_HOURS,
            'items' => $record->items->map(function (SaleItem $item) use ($returnedByLine) {
                $returned = (int) ($returnedByLine[$item->id] ?? 0);

                return [
                    'sale_item_id' => $item->id,
                    'name' => $item->medicine?->name,
                    'quantity' => $item->quantity,
                    'returned' => $returned,
                    'returnable' => $item->quantity - $returned,
                    // What the customer paid, which is what they are owed. The
                    // shelf price may well have moved since.
                    'unit_price' => (float) $item->price,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Refunds part of a sale.
     *
     * Restocks the exact batch the boxes came off, so returned stock keeps its
     * own expiry rather than joining an unrelated pile — except when it comes
     * back damaged, which is refunded and written off. Putting a broken box
     * back out for the next customer is the one outcome a pharmacy must not
     * allow, and quietly restocking it would be exactly that.
     */
    public function store(Request $request, int $sale): JsonResponse
    {
        $validated = $request->validate([
            'sale_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(SaleReturn::REASONS)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);

        return DB::transaction(function () use ($request, $validated, $pharmacy, $sale) {
            $record = Sale::where('pharmacy_id', $pharmacy->id)->lockForUpdate()->find($sale);

            if (! $record) {
                return $this->noSuchSale();
            }

            if ($record->created_at->diffInHours(now()) >= SaleReturn::WINDOW_HOURS) {
                return response()->json([
                    'message' => 'Returns are accepted within '
                        .SaleReturn::WINDOW_HOURS.' hours of the sale.',
                    'code' => 'return_window_closed',
                ], 409);
            }

            $line = SaleItem::where('sale_id', $record->id)
                ->with('medicine')
                ->find($validated['sale_item_id']);

            if (! $line) {
                return $this->noSuchSale();
            }

            $alreadyReturned = (int) SaleReturn::where('sale_item_id', $line->id)->sum('quantity');
            $remaining = $line->quantity - $alreadyReturned;

            if ($validated['quantity'] > $remaining) {
                return response()->json([
                    'message' => $remaining === 0
                        ? 'That line has already been returned in full.'
                        : 'Only '.$remaining.' of that line can still be returned.',
                    'code' => 'nothing_left_to_return',
                    'returnable_quantity' => $remaining,
                ], 409);
            }

            $actor = $request->user();
            // What the customer paid on the day, not what the box sells for now.
            $refund = (float) $line->price * $validated['quantity'];

            $return = SaleReturn::create([
                'pharmacy_id' => $pharmacy->id,
                'sale_id' => $record->id,
                'sale_item_id' => $line->id,
                'quantity' => $validated['quantity'],
                'refund_amount' => $refund,
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
                'pharmacist_id' => $actor instanceof Pharmacist ? $actor->id : null,
                'employee_id' => $actor instanceof Employee ? $actor->id : null,
            ]);

            if ($return->isRestockable() && $line->medicine) {
                $line->medicine->increment('quantity', $validated['quantity']);
            } elseif ($line->medicine) {
                // Refunded but never resold. Booked as a loss so the money is
                // accounted for rather than simply disappearing.
                StockWriteOff::create([
                    'pharmacy_id' => $pharmacy->id,
                    'medicine_id' => $line->medicine->id,
                    'medicine_name' => $line->medicine->name,
                    'unit_cost' => $line->cost_price,
                    'quantity' => $validated['quantity'],
                    'reason' => StockWriteOff::REASON_DAMAGED,
                    'note' => 'Returned damaged from sale #'.$record->id,
                    'pharmacist_id' => $actor instanceof Pharmacist ? $actor->id : null,
                    'employee_id' => $actor instanceof Employee ? $actor->id : null,
                ]);
            }

            Notification::notify(
                $pharmacy->id,
                'Sale returned',
                ($actor?->name ?? 'Someone').' refunded '
                    .$validated['quantity'].' x '.($line->medicine?->name ?? 'an item')
                    .' from sale #'.$record->id.' — '.$refund.' returned to the customer.',
                'sale_return',
                Notification::AUDIENCE_OWNER,
                $record->id,
            );

            return response()->json([
                'message' => 'Refunded.',
                'code' => 'sale_returned',
                'refund_amount' => $refund,
                'restocked' => $return->isRestockable(),
            ], 201);
        });
    }

    /**
     * Indistinguishable from a sale that does not exist.
     *
     * Answering differently for another pharmacy's sale would confirm the id is
     * real, and a sale id is a small enough number to walk through.
     */
    private function noSuchSale(): JsonResponse
    {
        return response()->json([
            'message' => 'The requested resource was not found.',
            'code' => 'not_found',
        ], 404);
    }
}
