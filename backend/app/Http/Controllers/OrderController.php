<?php

namespace App\Http\Controllers;

use App\Exceptions\PharmacyContextException;
use App\Exceptions\SupplierStockException;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderPlacementService;
use App\Services\PharmacyContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly OrderPlacementService $orderPlacement,
    ) {}

    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'payment_method' => 'required|in:cash,card',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $pharmacy = $this->pharmacyContext->resolve($request);

        DB::beginTransaction();

        try {
            $order = $this->orderPlacement->place(
                $pharmacy,
                (int) $request->supplier_id,
                $request->input('items'),
                $request->payment_method,
            );

            Notification::create([
                'pharmacy_id' => $pharmacy->id,
                'title' => 'طلب جديد',
                'message' => 'تم إنشاء طلب جديد من '.$order->supplier->name,
                'type' => 'order',
                'is_read' => false,
                'date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء الطلب بنجاح',
                'order_id' => $order->id,
                'total_price' => (float) $order->total_price,
                'status' => 'pending',
            ], 201);
        } catch (SupplierStockException $exception) {
            DB::rollBack();

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'supplier_stock_insufficient',
                'medicine' => $exception->details(),
            ], 400);
        } catch (InvalidArgumentException) {
            DB::rollBack();

            return response()->json(['message' => 'الدواء غير متوفر عند هذا المورد'], 400);
        } catch (AuthorizationException|ModelNotFoundException|PharmacyContextException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json(['message' => 'تعذر إنشاء الطلب'], 500);
        }
    }

    /**
     * What is about to arrive, and what it should be priced at.
     *
     * Read before receiving, because the pharmacist is the one who sets a shelf
     * price and until now nobody asked them: the supplier's suggested retail was
     * copied straight onto the stock row. The supplier's cost is theirs to set;
     * the margin is the pharmacy's.
     */
    public function receivingPlan(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['items.medicine', 'supplier'])->findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $order->pharmacy_id);
        Gate::forUser($request->user())->authorize('view', $order);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'This order has already been '.$order->status.'.',
                'code' => 'order_not_pending',
            ], 409);
        }

        $onShelf = Medicine::where('pharmacy_id', $order->pharmacy_id)
            ->whereIn('name', $order->items->map(fn (OrderItem $item) => $item->medicine?->name)->filter())
            ->get()
            ->keyBy('name');

        return response()->json([
            'order' => [
                'id' => $order->id,
                'supplier_name' => $order->supplier?->name,
                'total_price' => (float) $order->total_price,
                'payment_method' => $order->payment_method,
            ],
            'items' => $order->items
                ->filter(fn (OrderItem $item) => $item->medicine !== null)
                ->map(function (OrderItem $item) use ($onShelf) {
                    $existing = $onShelf->get($item->medicine->name);

                    return [
                        'medicine_id' => $item->medicine_id,
                        'name' => $item->medicine->name,
                        'category' => $item->medicine->category_medicine,
                        'quantity' => $item->quantity,
                        // The price agreed when the order was placed, not the
                        // catalogue's price today.
                        'unit_cost' => (float) $item->price,
                        // A drug already on the shelf keeps the price the
                        // pharmacy set for it; only a new one needs deciding.
                        'is_new' => $existing === null,
                        'current_selling_price' => $existing ? (float) $existing->selling_price : null,
                        'suggested_selling_price' => (float) ($existing?->selling_price ?? $item->medicine->selling_price),
                    ];
                })->values()->all(),
        ]);
    }

    public function receiveOrder(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            // Keyed by the catalogue medicine id the receiving plan returned.
            // Absent means "leave the price alone", which is what a restock of
            // something already on the shelf should do.
            'selling_prices' => ['sometimes', 'array'],
            'selling_prices.*' => ['numeric', 'min:0'],
        ]);

        $sellingPrices = $validated['selling_prices'] ?? [];

        DB::beginTransaction();

        try {
            $order = Order::with('items.medicine')->lockForUpdate()->findOrFail($id);
            $this->pharmacyContext->assertMatches($request, (int) $order->pharmacy_id);
            Gate::forUser($request->user())->authorize('update', $order);

            if ($order->status === 'received') {
                DB::rollBack();

                return response()->json(['message' => 'تم استلام الطلب مسبقاً'], 400);
            }

            if ($order->status === 'cancelled') {
                DB::rollBack();

                return response()->json(['message' => 'لا يمكن استلام طلب ملغي'], 400);
            }

            foreach ($order->items as $item) {
                $existingMedicine = Medicine::where('pharmacy_id', $order->pharmacy_id)
                    ->where('name', $item->medicine->name)
                    ->first();

                $chosenPrice = $sellingPrices[$item->medicine_id] ?? null;

                if ($existingMedicine) {
                    $existingMedicine->update([
                        'quantity' => $existingMedicine->quantity + $item->quantity,
                        'cost_price' => $this->blendedCost($existingMedicine, $item),
                        'selling_price' => $chosenPrice ?? $existingMedicine->selling_price,
                    ]);
                } else {
                    Medicine::create([
                        'pharmacy_id' => $order->pharmacy_id,
                        'supplier_id' => $order->supplier_id,
                        'name' => $item->medicine->name,
                        'category_medicine' => $item->medicine->category_medicine,
                        // What was actually paid, not what the catalogue asks
                        // today — the two drift apart the moment prices move.
                        'cost_price' => $item->price,
                        // The pharmacist's margin when they set one; otherwise
                        // the supplier's suggestion, as before.
                        'selling_price' => $chosenPrice ?? $item->medicine->selling_price,
                        'manufacturer' => $item->medicine->manufacturer,
                        'quantity' => $item->quantity,
                        'reorder_level' => $item->medicine->reorder_level,
                        'expire_date' => $item->medicine->expire_date,
                        'qr_code' => $item->medicine->qr_code,
                    ]);
                }
            }

            $order->update(['status' => 'received']);
            Notification::create([
                'pharmacy_id' => $order->pharmacy_id,
                'title' => 'تم استلام الطلب',
                'message' => 'تم استلام الطلب رقم '.$order->id.' وإضافته للمخزون',
                'type' => 'order',
                'is_read' => false,
                'date' => now(),
            ]);
            DB::commit();

            return response()->json(['message' => 'تم استلام الطلب وتحديث المخزون بنجاح']);
        } catch (AuthorizationException|ModelNotFoundException|PharmacyContextException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json(['message' => 'تعذر استلام الطلب'], 500);
        }
    }

    public function cancelOrder(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);
            $this->pharmacyContext->assertMatches($request, (int) $order->pharmacy_id);
            Gate::forUser($request->user())->authorize('update', $order);

            if ($order->status !== 'pending') {
                DB::rollBack();

                return response()->json(['message' => 'لا يمكن إلغاء الطلب، الحالة الحالية: '.$order->status], 400);
            }

            // Release the units reserved against the supplier catalogue when the
            // order was placed, so cancelling never loses supplier stock.
            foreach ($order->items as $item) {
                Medicine::whereNull('pharmacy_id')
                    ->where('id', $item->medicine_id)
                    ->increment('quantity', (int) $item->quantity);
            }

            $order->update(['status' => 'cancelled']);
            Notification::create([
                'pharmacy_id' => $order->pharmacy_id,
                'title' => 'تم إلغاء الطلب',
                'message' => 'تم إلغاء الطلب رقم '.$order->id,
                'type' => 'order',
                'is_read' => false,
                'date' => now(),
            ]);

            DB::commit();

            return response()->json(['message' => 'تم إلغاء الطلب بنجاح']);
        } catch (AuthorizationException|ModelNotFoundException|PharmacyContextException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json(['message' => 'تعذر إلغاء الطلب'], 500);
        }
    }

    /**
     * What the stock on the shelf costs once this delivery joins it.
     *
     * A weighted average of what is already there and what just arrived, which
     * is the only figure that is true of the mixed pile. Leaving the old cost
     * alone made every profit report drift further from reality as prices rose;
     * overwriting it with the newest price would be just as wrong the other way,
     * revaluing hundreds of cheaply bought boxes because one expensive one
     * turned up.
     *
     * Profit is revenue minus this figure at the moment of sale, so it is the
     * number the whole reporting side rests on.
     */
    private function blendedCost(Medicine $onShelf, OrderItem $arriving): float
    {
        $units = $onShelf->quantity + $arriving->quantity;

        if ($units <= 0) {
            return (float) $arriving->price;
        }

        $value = $onShelf->quantity * (float) $onShelf->cost_price
            + $arriving->quantity * (float) $arriving->price;

        return round($value / $units, 2);
    }

    public function getOrders(Request $request): JsonResponse
    {
        $request->validate(['pharmacy_id' => 'required|exists:pharmacies,id']);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        $orders = Order::with(['supplier', 'items.medicine'])
            ->where('pharmacy_id', $pharmacyId)
            ->latest()
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function getOrder(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['supplier', 'items.medicine'])->findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $order->pharmacy_id);
        Gate::forUser($request->user())->authorize('view', $order);

        return response()->json(['order' => $order]);
    }
}
