<?php

namespace App\Http\Controllers;

use App\Exceptions\ExpiredOfferException;
use App\Exceptions\SupplierStockException;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacy;
use App\Models\PurchaseCartItem;
use App\Services\OrderPlacementService;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The pharmacy's purchase cart: what it means to buy, before it has bought it.
 *
 * Holds offers from several suppliers at once and splits into an order per
 * supplier only at checkout — `orders.supplier_id` is singular, because a
 * supplier ships and invoices on its own.
 *
 * Nothing in here reserves stock or costs money, and that is what makes the
 * cart usable as an approval queue: the app can add a line when something runs
 * low and the pharmacist simply removes it if they disagree. There is no
 * separate accept/reject flow because the cart already is one.
 *
 * Every response returns the whole cart rather than the row that changed.
 * Quantities interact — a line's cheaper-elsewhere verdict depends on what the
 * supplier still has, and totals depend on every line — so answering with one
 * row would leave the client patching a view it cannot correctly patch.
 */
class PurchaseCartController extends Controller
{
    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly OrderPlacementService $orderPlacement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cart($this->pharmacyContext->resolve($request)));
    }

    /**
     * Puts a supplier's offer in the cart, or raises the quantity if it is
     * already there.
     *
     * Adding the same offer twice is someone asking for more of it, not for a
     * second line at the same price.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medicine_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);

        // Catalogue rows only. A pharmacy stock row carries an id too, and
        // buying from your own shelf is not a thing.
        $offer = Medicine::whereNull('pharmacy_id')->find($validated['medicine_id']);

        if (! $offer) {
            return response()->json([
                'message' => 'That medicine is not offered by any supplier.',
                'code' => 'not_a_supplier_offer',
            ], 422);
        }

        $item = PurchaseCartItem::firstOrNew([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $offer->id,
        ]);

        // A line the pharmacist touches becomes theirs, and stops being
        // presented as something the app suggested.
        $item->quantity = ($item->quantity ?? 0) + $validated['quantity'];
        $item->added_by = PurchaseCartItem::ADDED_BY_PHARMACIST;
        $item->save();

        return response()->json($this->cart($pharmacy));
    }

    /** Sets a line's quantity outright. Zero is a removal. */
    public function update(Request $request, int $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);
        $line = $this->line($pharmacy, $item);

        if (! $line) {
            return $this->noSuchLine();
        }

        if ($validated['quantity'] === 0) {
            $line->delete();

            return response()->json($this->cart($pharmacy));
        }

        $line->update([
            'quantity' => $validated['quantity'],
            'added_by' => PurchaseCartItem::ADDED_BY_PHARMACIST,
        ]);

        return response()->json($this->cart($pharmacy));
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);
        $line = $this->line($pharmacy, $item);

        if (! $line) {
            return $this->noSuchLine();
        }

        $line->delete();

        return response()->json($this->cart($pharmacy));
    }

    public function clear(Request $request): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->resolve($request);
        PurchaseCartItem::where('pharmacy_id', $pharmacy->id)->delete();

        return response()->json($this->cart($pharmacy));
    }

    /**
     * Moves a line to another supplier's offer of the same drug.
     *
     * How a saving is actually taken. Restricted to the same drug by name,
     * because this is a change of supplier, not a change of mind about what to
     * buy — swapping in a different product under the guise of a price switch
     * is how a pharmacy ends up with stock nobody ordered.
     */
    public function switchSupplier(Request $request, int $item): JsonResponse
    {
        $validated = $request->validate([
            'medicine_id' => ['required', 'integer'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);
        $line = $this->line($pharmacy, $item);

        if (! $line || ! $line->medicine) {
            return $this->noSuchLine();
        }

        $target = Medicine::whereNull('pharmacy_id')
            ->where('name', $line->medicine->name)
            ->find($validated['medicine_id']);

        if (! $target) {
            return response()->json([
                'message' => 'That supplier does not offer '.$line->medicine->name.'.',
                'code' => 'not_the_same_drug',
            ], 422);
        }

        if ($target->id === $line->medicine_id) {
            return response()->json($this->cart($pharmacy));
        }

        // The target may already be in the cart from an earlier decision. The
        // unique index forbids two lines for one offer, and two lines for the
        // same drug at the same price is not what anyone meant anyway.
        $existing = PurchaseCartItem::where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $target->id)
            ->first();

        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + $line->quantity,
                'added_by' => PurchaseCartItem::ADDED_BY_PHARMACIST,
            ]);
            $line->delete();

            return response()->json($this->cart($pharmacy));
        }

        $line->update([
            'medicine_id' => $target->id,
            'added_by' => PurchaseCartItem::ADDED_BY_PHARMACIST,
        ]);

        return response()->json($this->cart($pharmacy));
    }

    /**
     * Buys the cart: one order per supplier, all of them or none.
     *
     * All-or-nothing because a partially bought cart is the hardest state to
     * explain and to undo — the pharmacist would be left working out which
     * supplier went through and what is still owed. Being told nothing happened
     * and why is recoverable in one action.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,card'],
            // Paying a wholesaler by card asks for the card, exactly as the
            // till does when a customer pays by one. Validated and then thrown
            // away: a card number is not something this system should keep, and
            // no column anywhere stores it.
            'card_number' => ['required_if:payment_method,card', 'digits:10'],
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);

        $items = PurchaseCartItem::where('pharmacy_id', $pharmacy->id)
            ->with('medicine')
            ->get()
            ->filter(fn (PurchaseCartItem $item) => $item->medicine !== null);

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'There is nothing in the cart to buy.',
                'code' => 'cart_empty',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $placed = [];

            foreach ($items->groupBy(fn (PurchaseCartItem $item) => $item->medicine->supplier_id) as $supplierId => $lines) {
                $order = $this->orderPlacement->place(
                    $pharmacy,
                    (int) $supplierId,
                    $lines->map(fn (PurchaseCartItem $line) => [
                        'medicine_id' => $line->medicine_id,
                        'quantity' => $line->quantity,
                    ])->all(),
                    $validated['payment_method'],
                );

                $placed[] = [
                    'id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'supplier_name' => $order->supplier?->name,
                    'total_price' => (float) $order->total_price,
                    'item_count' => $lines->count(),
                ];
            }

            PurchaseCartItem::where('pharmacy_id', $pharmacy->id)->delete();

            $total = array_sum(array_column($placed, 'total_price'));

            // One message for one act of buying. A notification per supplier
            // would bury the fact that this was a single decision.
            Notification::create([
                'pharmacy_id' => $pharmacy->id,
                'title' => 'Purchase placed',
                'message' => count($placed) === 1
                    ? 'Ordered '.$items->count().' item(s) from '.$placed[0]['supplier_name'].' for '.$total.'.'
                    : 'Ordered '.$items->count().' item(s) from '.count($placed).' suppliers for '.$total.'.',
                'type' => 'order',
                'audience' => Notification::AUDIENCE_OWNER,
                'is_read' => false,
                'date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Your order has been placed.',
                'code' => 'purchase_placed',
                'orders' => $placed,
                'total' => $total,
            ], 201);
        } catch (SupplierStockException $exception) {
            DB::rollBack();

            // Nothing was bought and the cart is untouched, so the pharmacist
            // can lower this one line and try again.
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'supplier_stock_insufficient',
                'medicine' => $exception->details(),
            ], 409);
        } catch (ExpiredOfferException $exception) {
            DB::rollBack();

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'medicine_expired',
                'medicine' => $exception->details(),
            ], 409);
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json([
                'message' => 'Your order could not be placed.',
                'code' => 'checkout_failed',
            ], 500);
        }
    }

    /**
     * The whole cart, grouped the way it will be bought.
     *
     * @return array<string, mixed>
     */
    private function cart(Pharmacy $pharmacy): array
    {
        $items = PurchaseCartItem::where('pharmacy_id', $pharmacy->id)
            ->with(['medicine.supplier:id,name,phone,address'])
            ->get()
            // A line whose catalogue row was deleted has nothing left to buy.
            ->filter(fn (PurchaseCartItem $item) => $item->medicine !== null)
            ->values();

        $alternatives = $this->cheaperAlternatives($items);

        $groups = $items
            ->groupBy(fn (PurchaseCartItem $item) => $item->medicine->supplier_id)
            ->map(fn (Collection $lines) => [
                'supplier' => [
                    'id' => $lines->first()->medicine->supplier?->id,
                    'name' => $lines->first()->medicine->supplier?->name,
                    'phone' => $lines->first()->medicine->supplier?->phone,
                    'address' => $lines->first()->medicine->supplier?->address,
                ],
                'items' => $lines->map(fn (PurchaseCartItem $item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal(),
                    'added_by' => $item->added_by,
                    'available' => $item->isAvailable(),
                    'expired' => $item->isExpired(),
                    'expiring_soon' => $item->isExpiringSoon(),
                    'medicine' => [
                        'id' => $item->medicine->id,
                        'name' => $item->medicine->name,
                        'category' => $item->medicine->category_medicine,
                        'manufacturer' => $item->medicine->manufacturer,
                        'cost_price' => (float) $item->medicine->cost_price,
                        'suggested_retail' => (float) $item->medicine->selling_price,
                        'available_quantity' => $item->medicine->quantity,
                        'expire_date' => $item->medicine->expire_date?->toDateString(),
                    ],
                    'cheaper_elsewhere' => $alternatives[$item->id] ?? null,
                ])->values()->all(),
                'subtotal' => $lines->sum(fn (PurchaseCartItem $item) => $item->subtotal()),
            ])
            ->values()
            ->all();

        return [
            'suppliers' => $groups,
            'total' => $items->sum(fn (PurchaseCartItem $item) => $item->subtotal()),
            'item_count' => $items->count(),
            // How many lines the pharmacist has not yet looked at, so the app
            // can say "3 items added for you" rather than making them hunt.
            'suggested_count' => $items->where('added_by', PurchaseCartItem::ADDED_BY_APP)->count(),
            'unavailable_count' => $items->reject(fn (PurchaseCartItem $item) => $item->isAvailable())->count(),
            // Checkout will refuse these outright, so the cart has to say so
            // before the pharmacist presses Buy and is turned away.
            'expired_count' => $items->filter(fn (PurchaseCartItem $item) => $item->isExpired())->count(),
            'expiring_soon_count' => $items->filter(fn (PurchaseCartItem $item) => $item->isExpiringSoon())->count(),
        ];
    }

    /**
     * For each line, the same drug going cheaper at another supplier.
     *
     * Derived at read time and never stored: the catalogue is shared and its
     * prices move, so a saving written down when the line was added would be
     * out of date by the time anyone acted on it.
     *
     * Matched on the drug's name, which is also how {@see OrderController} maps
     * an arriving order onto existing stock. An alternative that cannot fill the
     * line in full is not an alternative, so short suppliers are skipped rather
     * than advertised and then refused at checkout — as are expired ones, which
     * are frequently the cheapest and would otherwise win every comparison.
     *
     * @param  Collection<int, PurchaseCartItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function cheaperAlternatives(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $offers = Medicine::query()
            ->whereNull('pharmacy_id')
            ->whereIn('name', $items->map(fn (PurchaseCartItem $item) => $item->medicine->name)->unique())
            ->where(function ($query) {
                $query->whereNull('expire_date')
                    ->orWhereDate('expire_date', '>=', now()->startOfDay());
            })
            ->with('supplier:id,name')
            ->get()
            ->groupBy('name');

        $found = [];

        foreach ($items as $item) {
            $best = ($offers[$item->medicine->name] ?? collect())
                ->filter(fn (Medicine $offer) => $offer->quantity >= $item->quantity)
                ->sortBy(fn (Medicine $offer) => (float) $offer->cost_price)
                ->first();

            if (! $best || $best->id === $item->medicine->id) {
                continue;
            }

            $saving = ((float) $item->medicine->cost_price - (float) $best->cost_price) * $item->quantity;

            if ($saving <= 0) {
                continue;
            }

            $found[$item->id] = [
                'medicine_id' => $best->id,
                'supplier_id' => $best->supplier_id,
                'supplier_name' => $best->supplier?->name,
                'cost_price' => (float) $best->cost_price,
                'saving' => $saving,
            ];
        }

        return $found;
    }

    private function line(Pharmacy $pharmacy, int $id): ?PurchaseCartItem
    {
        return PurchaseCartItem::where('pharmacy_id', $pharmacy->id)->find($id);
    }

    /**
     * Indistinguishable from a line that never existed.
     *
     * Answering differently for another pharmacy's cart line would confirm that
     * the id is real.
     */
    private function noSuchLine(): JsonResponse
    {
        return response()->json([
            'message' => 'That cart line was not found.',
            'code' => 'not_found',
        ], 404);
    }
}
