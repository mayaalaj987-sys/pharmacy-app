<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Medicine;
use App\Models\Employee;
use App\Models\StockWriteOff;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    private function getDateRange(string $filter): array
    {
        return match($filter) {
            'daily'   => [now()->startOfDay(),   now()->endOfDay()],
            'weekly'  => [now()->startOfWeek(),  now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly'  => [now()->startOfYear(),  now()->endOfYear()],
            default   => [now()->startOfDay(),   now()->endOfDay()],
        };
    }

    // ===== الإيرادات =====
    public function getRevenue(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter'      => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $revenue = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_price');

        return response()->json([
            'filter'  => $request->filter,
            'revenue' => $revenue,
        ]);
    }

    // ===== قيمة المخزون =====
    public function getInventoryValue(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        $inventoryValue = Medicine::where('pharmacy_id', $pharmacyId)
            ->selectRaw('SUM(cost_price * quantity) as total_cost, SUM(selling_price * quantity) as total_selling')
            ->first();

        // Stock past its date is counted in the total but broken out beside it.
        // The till refuses to sell it, so calling it inventory value without
        // qualification tells the pharmacist they hold money they do not.
        $expired = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereNotNull('expire_date')
            ->whereDate('expire_date', '<', now()->startOfDay())
            ->selectRaw('SUM(cost_price * quantity) as dead_cost')
            ->value('dead_cost') ?? 0;

        $expiringSoon = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereNotNull('expire_date')
            ->whereDate('expire_date', '>=', now()->startOfDay())
            ->whereDate('expire_date', '<=', now()->addMonths(3))
            ->selectRaw('SUM(cost_price * quantity) as at_risk')
            ->value('at_risk') ?? 0;

        return response()->json([
            'total_cost_value'    => $inventoryValue->total_cost    ?? 0,
            'total_selling_value' => $inventoryValue->total_selling ?? 0,
            'expired_cost_value'  => round((float) $expired, 2),
            'expiring_cost_value' => round((float) $expiringSoon, 2),
        ]);
    }

    // ===== متوسط المبيعات =====
    // ✅ FIX: الحين بيرجع متوسط عدد العمليات ÷ 7 (متوسط يومي خلال الأسبوع)
    public function getAverageSales(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        // دائماً weekly — متوسط يومي خلال الأسبوع الحالي
        $start = now()->startOfWeek();
        $end   = now()->endOfWeek();

        $totalSalesThisWeek = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // عدد الأيام اللي مرت من الأسبوع (بحيث ما نقسم على أيام مستقبلية)
        $daysPassed = now()->dayOfWeek === 0 ? 7 : now()->dayOfWeek; // الأحد = 0 نعتبره 7

        $dailyAverage = round($totalSalesThisWeek / 7, 2); // متوسط على 7 أيام

        return response()->json([
            'week_start'           => $start->toDateString(),
            'week_end'             => $end->toDateString(),
            'total_sales_week'     => $totalSalesThisWeek,   // إجمالي عمليات الأسبوع
            'daily_average'        => $dailyAverage,          // متوسط يومي = عدد العمليات ÷ 7
        ]);
    }

    // ===== الأرباح =====
    public function getProfits(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter'      => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $revenue = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_price');

        // Read off the sale line, not off the medicine. Receiving blends a
        // drug's recorded cost when fresh stock arrives, so joining back to
        // `medicines` made a finished sale reprice itself and last year's
        // profit stop being last year's number.
        $costOfGoods = SaleItem::whereHas('sale', function ($query) use ($pharmacyId, $start, $end) {
            $query->where('pharmacy_id', $pharmacyId)
                ->whereBetween('created_at', [$start, $end]);
        })
            ->selectRaw('SUM(sale_items.quantity * sale_items.cost_price) as total_cost')
            ->first();

        // Stock bought and thrown away rather than sold. It never entered cost
        // of goods, so without this its cost left the books entirely: it did
        // not reduce profit when bought, did not reduce profit when discarded,
        // and sat in the inventory valuation as an asset. Real money, invisible.
        $losses = StockWriteOff::where('pharmacy_id', $pharmacyId)
            ->counted()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('SUM(quantity * unit_cost) as total_loss')
            ->value('total_loss') ?? 0;

        // ✅ FIX: الرواتب نحسبها نسبة للفترة الزمنية مو كاملة
        $monthlySalaries = Employee::where('pharmacy_id', $pharmacyId)
            ->where('status', Employee::STATUS_APPROVED)
            ->where('role', Employee::ROLE_EMPLOYEE)
            ->sum('salary');

        $salaryForPeriod = match($request->filter) {
            'daily'   => $monthlySalaries / 30,
            'weekly'  => $monthlySalaries / 4,
            'monthly' => $monthlySalaries,
            'yearly'  => $monthlySalaries * 12,
        };

        $profit = $revenue - ($costOfGoods->total_cost ?? 0) - $salaryForPeriod - $losses;

        return response()->json([
            'filter'          => $request->filter,
            'revenue'         => $revenue,
            'cost_of_goods'   => $costOfGoods->total_cost ?? 0,
            'salaries'        => round($salaryForPeriod, 2),
            'write_offs'      => round((float) $losses, 2),
            'profit'          => round($profit, 2),
        ]);
    }

    // ===== الأدوية الأكثر مبيعاً =====
    public function getMostSoldMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter'      => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $medicines = SaleItem::whereHas('sale', function ($query) use ($pharmacyId, $start, $end) {
            $query->where('pharmacy_id', $pharmacyId)
                ->whereBetween('created_at', [$start, $end]);
        })
            ->selectRaw('medicine_id, SUM(quantity) as total_sold')
            ->groupBy('medicine_id')
            ->orderByDesc('total_sold')
            ->with('medicine:id,name,category_medicine')
            ->get()
            ->map(fn($item) => [
                'medicine'   => $item->medicine->name,
                'category'   => $item->medicine->category_medicine,
                'total_sold' => $item->total_sold,
            ]);

        return response()->json([
            'filter'    => $request->filter,
            'medicines' => $medicines,
        ]);
    }

    // ===== الأكثر مبيعاً بالفئة =====
    public function getMostSoldByCategory(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter'      => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $categories = SaleItem::whereHas('sale', function ($query) use ($pharmacyId, $start, $end) {
            $query->where('pharmacy_id', $pharmacyId)
                ->whereBetween('created_at', [$start, $end]);
        })
            ->selectRaw('medicines.category_medicine, SUM(sale_items.quantity) as total_sold')
            ->join('medicines', 'sale_items.medicine_id', '=', 'medicines.id')
            ->groupBy('medicines.category_medicine')
            ->orderByDesc('total_sold')
            ->get();

        return response()->json([
            'filter'     => $request->filter,
            'categories' => $categories,
        ]);
    }

    // ===== NEW: داشبورد اليوم =====
    // ✅ NEW: endpoint واحد يجمع كل مستجدات اليوم للصيدلاني
    public function getDashboard(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        $today      = now()->toDateString();
        $start      = now()->startOfDay();
        $end        = now()->endOfDay();

        // عدد مبيعات اليوم
        $todaySalesCount = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // إيرادات اليوم
        $todayRevenue = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_price');

        // أرباح اليوم
        $costOfGoods = SaleItem::whereHas('sale', function ($q) use ($pharmacyId, $start, $end) {
            $q->where('pharmacy_id', $pharmacyId)->whereBetween('created_at', [$start, $end]);
        })
            ->join('medicines', 'sale_items.medicine_id', '=', 'medicines.id')
            ->selectRaw('SUM(sale_items.quantity * medicines.cost_price) as total_cost')
            ->first();

        $dailySalary = Employee::where('pharmacy_id', $pharmacyId)
                ->where('status', Employee::STATUS_APPROVED)
                ->where('role', Employee::ROLE_EMPLOYEE)
                ->sum('salary') / 30;

        $todayProfit = $todayRevenue - ($costOfGoods->total_cost ?? 0) - $dailySalary;

        // عدد الأدوية التي ستنتهي صلاحيتها خلال 3 أشهر
        $expiringCount = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereDate('expire_date', '<=', now()->addMonths(3))
            ->whereDate('expire_date', '>=', now())
            ->count();

        // عدد الأدوية الناقصة (كمية <= reorder_level)
        $lowStockCount = Medicine::where('pharmacy_id', $pharmacyId)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        return response()->json([
            'date'              => $today,
            'today_sales_count' => $todaySalesCount,
            'today_revenue'     => round($todayRevenue, 2),
            'today_profit'      => round($todayProfit, 2),
            'expiring_count'    => $expiringCount,
            'low_stock_count'   => $lowStockCount,
        ]);
    }
}
