<?php

namespace App\Http\Controllers;

use App\Exceptions\PharmacyContextException;
use App\Exceptions\SupplierStockException;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Order;
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

    public function receiveOrder(Request $request, int $id): JsonResponse
    {
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

                if ($existingMedicine) {
                    $existingMedicine->increment('quantity', $item->quantity);
                } else {
                    Medicine::create([
                        'pharmacy_id' => $order->pharmacy_id,
                        'supplier_id' => $order->supplier_id,
                        'name' => $item->medicine->name,
                        'category_medicine' => $item->medicine->category_medicine,
                        'cost_price' => $item->medicine->cost_price,
                        'selling_price' => $item->medicine->selling_price,
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
