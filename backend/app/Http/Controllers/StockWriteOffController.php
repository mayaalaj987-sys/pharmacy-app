<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\StockWriteOff;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Taking stock off the shelf when it was not sold.
 *
 * Inventory could only ever go up by receiving and down by selling. Everything
 * else that actually happens to stock — it expires, it breaks, it goes missing
 * — had nowhere to go but a pharmacist quietly editing the quantity, which
 * records neither what happened nor what it cost.
 *
 * That silence had a price. Stock bought and never sold never entered cost of
 * goods, so its cost left the books entirely: it did not reduce profit when it
 * was bought, it did not reduce profit when it was thrown away, and it stayed
 * in the inventory valuation as an asset. This is how the money is found again.
 */
class StockWriteOffController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    /** Every loss the pharmacy has booked, newest first. */
    public function index(Request $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);

        $writeOffs = StockWriteOff::where('pharmacy_id', $pharmacy->id)
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'write_offs' => $writeOffs->map(fn (StockWriteOff $writeOff) => [
                'id' => $writeOff->id,
                'medicine_name' => $writeOff->medicine_name,
                'quantity' => $writeOff->quantity,
                'unit_cost' => (float) $writeOff->unit_cost,
                'value' => $writeOff->value(),
                'reason' => $writeOff->reason,
                'note' => $writeOff->note,
                'recorded_at' => $writeOff->created_at?->toISOString(),
            ])->all(),
            // What it has cost this pharmacy so far. Returns to the supplier are
            // left out: those are replaced or refunded, not lost.
            'total_value' => StockWriteOff::where('pharmacy_id', $pharmacy->id)
                ->counted()
                ->get()
                ->sum(fn (StockWriteOff $writeOff) => $writeOff->value()),
        ]);
    }

    /**
     * Removes stock from one batch and books the loss.
     *
     * Scoped to a batch rather than a drug on purpose. It is a specific pile of
     * boxes that expired or broke, and the cost recorded has to be the cost of
     * those boxes — a drug held in two batches bought at different prices has
     * no single answer to "what did this cost".
     */
    public function store(Request $request, int $medicine): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(StockWriteOff::REASONS)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);

        return DB::transaction(function () use ($request, $validated, $pharmacy, $medicine) {
            $batch = Medicine::where('pharmacy_id', $pharmacy->id)
                ->lockForUpdate()
                ->find($medicine);

            if (! $batch) {
                return response()->json([
                    'message' => 'The requested resource was not found.',
                    'code' => 'not_found',
                ], 404);
            }

            if ($batch->quantity < $validated['quantity']) {
                return response()->json([
                    'message' => 'Only '.$batch->quantity.' of '.$batch->name.' are on the shelf.',
                    'code' => 'insufficient_stock',
                    'medicine' => [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'available_quantity' => $batch->quantity,
                    ],
                ], 409);
            }

            $actor = $request->user();

            $writeOff = StockWriteOff::create([
                'pharmacy_id' => $pharmacy->id,
                'medicine_id' => $batch->id,
                // Copied, not joined: the name and the cost both move, and a
                // loss booked in March must still read as it did in March.
                'medicine_name' => $batch->name,
                'unit_cost' => $batch->cost_price,
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
                'pharmacist_id' => $actor instanceof Pharmacist ? $actor->id : null,
                'employee_id' => $actor instanceof Employee ? $actor->id : null,
            ]);

            $batch->decrement('quantity', $validated['quantity']);

            // The owner should hear about money leaving, whoever booked it.
            Notification::notify(
                $pharmacy->id,
                'Stock written off',
                ($actor?->name ?? 'Someone').' wrote off '
                    .$validated['quantity'].' x '.$batch->name
                    .' ('.str_replace('_', ' ', $validated['reason']).') — '
                    .$writeOff->value().' lost.',
                'write_off',
                Notification::AUDIENCE_OWNER,
                $batch->id,
            );

            return response()->json([
                'message' => 'Written off. The loss has been recorded.',
                'code' => 'stock_written_off',
                'write_off' => [
                    'id' => $writeOff->id,
                    'quantity' => $writeOff->quantity,
                    'value' => $writeOff->value(),
                ],
                'remaining_quantity' => $batch->fresh()->quantity,
            ], 201);
        });
    }
}
