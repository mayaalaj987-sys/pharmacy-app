<?php

namespace App\Http\Controllers;

use App\Exceptions\StockAllocationException;
use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\PharmacyContextResolver;
use App\Services\PurchaseCartAutoStocker;
use App\Services\StockAllocator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly PurchaseCartAutoStocker $autoStocker,
        private readonly StockAllocator $allocator,
    ) {}

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
            // Worked out for the whole basket before anything is written, so a
            // shortage on the last line does not leave the earlier ones sold.
            $plans = [];
            $totalPrice = 0;

            foreach ($request->input('items') as $index => $item) {
                $plan = $this->allocator->allocate(
                    $pharmacy,
                    (int) $item['medicine_id'],
                    (int) $item['quantity'],
                );

                // One price for the line however many batches fill it. The
                // batch about to be sold sets it — which is the price the till
                // displayed, since the screen shows what it will sell first.
                $unitPrice = (float) $plan[0]['batch']->selling_price;

                $plans[$index] = ['plan' => $plan, 'unit_price' => $unitPrice];
                $totalPrice += $unitPrice * (int) $item['quantity'];
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

            foreach ($plans as ['plan' => $plan, 'unit_price' => $unitPrice]) {
                foreach ($plan as ['batch' => $batch, 'quantity' => $taken]) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'medicine_id' => $batch->id,
                        'quantity' => $taken,
                        'price' => $unitPrice,
                        // The cost of these particular boxes, frozen. Receiving
                        // blends the recorded cost when fresh stock arrives, so
                        // reading it back later would reprice a finished sale.
                        'cost_price' => $batch->cost_price,
                    ]);
                    $batch->decrement('quantity', $taken);
                }

                // Judged on the drug, not on the batch that happened to run
                // out: a pharmacy holding 200 boxes across two batches is not
                // low because the older one is down to three.
                $anchor = $plan[0]['batch']->fresh();

                if (! $this->autoStocker->consider($pharmacy, $anchor)) {
                    $this->createStockNotification($pharmacy->id, $anchor);
                }
            }

            // Only when somebody else rang it up.
            //
            // A notification for every sale made the bell useless: a working
            // pharmacy rings up a hundred a day, and "a sale happened" told the
            // owner nothing they did not already know — while burying the one
            // message that mattered. What an owner cannot see for themselves is
            // what their staff sold while they were out, so that is the only
            // sale worth interrupting them for.
            $seller = $request->user();

            if ($employeeId !== null) {
                Notification::notify(
                    $pharmacy->id,
                    'Sale by '.($seller?->name ?? 'staff'),
                    ($seller?->name ?? 'A member of staff').' sold '
                        .count($request->input('items')).' item(s) for '.$totalPrice.'.',
                    'sale',
                    Notification::AUDIENCE_OWNER,
                    $sale->id,
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'تمت عملية البيع بنجاح',
                'sale_id' => $sale->id,
                'total_price' => $totalPrice,
                'items_count' => count($request->input('items')),
                'payment_method' => $request->payment_method,
                'date' => $sale->date,
            ], 201);
        } catch (StockAllocationException $exception) {
            DB::rollBack();

            // Expired stock must never leave the pharmacy. The till blocks it
            // too, but the guarantee has to live on the server.
            if ($exception->reason === StockAllocationException::EXPIRED) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'medicine_expired',
                    'medicine' => [
                        'name' => $exception->drug,
                        'expire_date' => $exception->expiredOn,
                    ],
                ], 400);
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'insufficient_stock',
                'medicine' => [
                    'name' => $exception->drug,
                    'available_quantity' => $exception->available,
                ],
            ], 400);
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
        // Every batch of the drug, not the one this sale drew from. A delivery
        // with a different expiry date is its own row, so the batch that just
        // ran out says nothing about what is left on the shelf.
        $onHand = (int) Medicine::where('pharmacy_id', $pharmacyId)
            ->where('name', $medicine->name)
            ->sum('quantity');

        $type = $onHand === 0 ? 'out_of_stock' : ($onHand <= $medicine->reorder_level ? 'low_stock' : null);

        if (! $type) {
            return;
        }

        // Only silent about a warning already given recently.
        //
        // This used to search the whole history, so a drug reported low once
        // was never reported again — restock it, sell it out, restock it, and
        // the pharmacy is never told a second time. A week is long enough that
        // the same shortage is not repeated daily and short enough that the
        // next one is heard.
        $recentlyWarned = Notification::where('pharmacy_id', $pharmacyId)
            ->where('type', $type)
            ->where('message', 'LIKE', $medicine->name.' %')
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        if ($recentlyWarned) {
            return;
        }

        Notification::notify(
            $pharmacyId,
            $type === 'out_of_stock' ? 'Out of stock' : 'Running low',
            $type === 'out_of_stock'
                ? $medicine->name.' is out of stock.'
                : $medicine->name.' is down to '.$onHand.'.',
            $type,
            Notification::AUDIENCE_STAFF,
            $medicine->id,
        );
    }
}
