<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\PharmacyContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaleController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    public function createSale(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'pharmacist_id' => 'nullable|exists:pharmacists,id',
            'employee_id' => 'nullable|exists:employees,id',
            'customer_name' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,insurance',
            'card_number' => 'required_if:payment_method,card|digits:10',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);
        [$pharmacistId, $employeeId] = $this->trustedActorIds($request);

        DB::beginTransaction();

        try {
            $medicines = [];
            $totalPrice = 0;

            foreach ($request->input('items') as $item) {
                $medicine = Medicine::where('pharmacy_id', $pharmacy->id)
                    ->lockForUpdate()
                    ->findOrFail($item['medicine_id']);
                $medicines[$medicine->id] = $medicine;

                if ($medicine->quantity < $item['quantity']) {
                    DB::rollBack();

                    return response()->json(['message' => 'الكمية غير متوفرة: '.$medicine->name], 400);
                }

                // Expired stock must never leave the pharmacy. The client also
                // blocks this, but the guarantee has to live on the server.
                if ($medicine->expire_date !== null && $medicine->expire_date->isBefore(now()->startOfDay())) {
                    DB::rollBack();

                    return response()->json([
                        'message' => $medicine->name.' has expired and cannot be sold.',
                        'code' => 'medicine_expired',
                        'medicine' => [
                            'id' => $medicine->id,
                            'name' => $medicine->name,
                            'expire_date' => $medicine->expire_date->toDateString(),
                        ],
                    ], 400);
                }

                $totalPrice += $medicine->selling_price * $item['quantity'];
            }

            if ($request->payment_method === 'insurance') {
                $totalPrice *= 0.80;
            }

            $sale = Sale::create([
                'pharmacy_id' => $pharmacy->id,
                'pharmacist_id' => $pharmacistId,
                'employee_id' => $employeeId,
                'customer_name' => $request->customer_name,
                'payment_method' => $request->payment_method,
                'total_price' => $totalPrice,
                'date' => now()->toDateString(),
            ]);

            foreach ($request->input('items') as $item) {
                $medicine = $medicines[$item['medicine_id']];
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $medicine->id,
                    'quantity' => $item['quantity'],
                    'price' => $medicine->selling_price,
                ]);
                $medicine->decrement('quantity', $item['quantity']);
                $medicine->refresh();
                $this->createStockNotification($pharmacy->id, $medicine);
            }

            // Names the seller. "A sale happened" told the owner nothing they
            // could act on; who rang it up is the whole point of the message.
            $seller = $request->user();
            Notification::create([
                'pharmacy_id' => $pharmacy->id,
                'title' => 'New sale',
                'message' => ($seller?->name ?? 'Someone').' sold '
                    .count($request->input('items')).' item(s) for '.$totalPrice.'.',
                'type' => 'sale',
                'audience' => Notification::AUDIENCE_OWNER,
                'is_read' => false,
                'date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تمت عملية البيع بنجاح',
                'sale_id' => $sale->id,
                'total_price' => $totalPrice,
                'items_count' => count($request->input('items')),
                'payment_method' => $request->payment_method,
                'date' => $sale->date,
            ], 201);
        } catch (ModelNotFoundException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json(['message' => 'حدث خطأ أثناء إنشاء عملية البيع'], 500);
        }
    }

    public function getDailySales(Request $request): JsonResponse
    {
        $pharmacyId = $this->validatedPharmacyId($request);
        $sales = Sale::where('pharmacy_id', $pharmacyId)
            ->whereDate('date', now()->toDateString())
            ->with('items.medicine')
            ->get();

        return response()->json([
            'date' => now()->toDateString(),
            'total_sales' => $sales->count(),
            'total_price' => $sales->sum('total_price'),
            'sales' => $sales,
        ]);
    }

    public function getAllSales(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'nullable|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        $query = Sale::where('pharmacy_id', $pharmacyId)->with('items.medicine');
        $this->applyDateFilter($query, $request->input('filter'));
        $sales = $query->latest()->get();

        return response()->json([
            'total_sales' => $sales->count(),
            'total_price' => $sales->sum('total_price'),
            'sales' => $sales,
        ]);
    }

    public function getEmployeeSales(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'filter' => 'nullable|in:daily,weekly,monthly,yearly',
        ]);
        $employee = $request->user();

        if (! $employee instanceof Employee || (int) $request->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('You cannot access another employee\'s sales.');
        }

        $this->pharmacyContext->resolve($request);
        $query = Sale::where('employee_id', $employee->id)
            ->where('pharmacy_id', $employee->pharmacy_id)
            ->with('items.medicine');
        $this->applyDateFilter($query, $request->input('filter'));
        $sales = $query->latest()->get();

        return response()->json([
            'total_sales' => $sales->count(),
            'total_price' => $sales->sum('total_price'),
            'sales' => $sales,
        ]);
    }

    private function trustedActorIds(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof Pharmacist) {
            if (($request->filled('pharmacist_id') && (int) $request->pharmacist_id !== (int) $user->id)
                || $request->filled('employee_id')) {
                throw new AuthorizationException('Sale actor identifiers do not match the authenticated user.');
            }

            return [$user->id, null];
        }

        if ($user instanceof Employee) {
            if (($request->filled('employee_id') && (int) $request->employee_id !== (int) $user->id)
                || $request->filled('pharmacist_id')) {
                throw new AuthorizationException('Sale actor identifiers do not match the authenticated user.');
            }

            return [null, $user->id];
        }

        throw new AuthorizationException('Unauthenticated.');
    }

    private function validatedPharmacyId(Request $request): int
    {
        $request->validate(['pharmacy_id' => 'required|exists:pharmacies,id']);

        return $this->pharmacyContext->resolve($request)->id;
    }

    private function applyDateFilter($query, ?string $filter): void
    {
        if (! $filter) {
            return;
        }

        $query->whereBetween('created_at', match ($filter) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
        });
    }

    private function createStockNotification(int $pharmacyId, Medicine $medicine): void
    {
        $type = $medicine->quantity === 0 ? 'out_of_stock' : ($medicine->quantity <= $medicine->reorder_level ? 'low_stock' : null);

        if (! $type || Notification::where('pharmacy_id', $pharmacyId)
            ->where('type', $type)
            ->where('message', 'LIKE', '%'.$medicine->name.'%')
            ->exists()) {
            return;
        }

        Notification::create([
            'pharmacy_id' => $pharmacyId,
            'audience' => Notification::AUDIENCE_STAFF,
            'title' => $type === 'out_of_stock' ? 'Out of stock' : 'Running low',
            'message' => $type === 'out_of_stock'
                ? $medicine->name.' is out of stock.'
                : $medicine->name.' is down to '.$medicine->quantity.'.',
            'type' => $type,
            'is_read' => false,
            'date' => now(),
        ]);
    }
}
