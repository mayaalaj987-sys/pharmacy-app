<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockWriteOff;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    private function getDateRange(string $filter): array
    {
        return match ($filter) {
            'daily' => [now()->startOfDay(),   now()->endOfDay()],
            'weekly' => [now()->startOfWeek(),  now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly' => [now()->startOfYear(),  now()->endOfYear()],
            default => [now()->startOfDay(),   now()->endOfDay()],
        };
    }

    // ===== الإيرادات =====
    public function getRevenue(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $revenue = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_price');

        // Booked in the period the customer brought it back, which is standard
        // and also the only workable rule: a refund given today against a sale
        // from last month cannot retrospectively change last month's takings.
        $refunds = $this->refundsIn($pharmacyId, $start, $end);

        return response()->json([
            'filter' => $request->filter,
            'revenue' => round((float) $revenue - $refunds, 2),
            'gross_revenue' => round((float) $revenue, 2),
            'refunds' => round($refunds, 2),
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
            'total_cost_value' => $inventoryValue->total_cost ?? 0,
            'total_selling_value' => $inventoryValue->total_selling ?? 0,
            'expired_cost_value' => round((float) $expired, 2),
            'expiring_cost_value' => round((float) $expiringSoon, 2),
        ]);
    }

    /**
     * What a typical sale is worth, and how many there were.
     *
     * Rewritten. It used to count sales rather than value them, divide by seven
     * regardless, and ignore the requested period entirely — so a screen
     * showing "Year" was handed this week's figure. What a pharmacist wants
     * from "average order" is the size of a typical basket, which is the number
     * that tells them whether people are buying one box or five.
     */
    public function getAverageSales(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $sales = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end]);

        $count = (clone $sales)->count();
        $total = (float) (clone $sales)->sum('total_price');

        return response()->json([
            'filter' => $request->filter,
            'sales_count' => $count,
            'total' => round($total, 2),
            // Gross, not net of refunds: the question is how big a basket is,
            // and a basket that was later brought back was still that big.
            'average_sale' => $count > 0 ? round($total / $count, 2) : 0,
        ]);
    }

    // ===== الأرباح =====
    public function getProfits(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
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
        //
        // Broken down by reason too: a single total says money is leaving, not
        // whether it is expiry (a buying problem), damage (a handling problem)
        // or loss (a problem with the count itself) — three different things to
        // go fix. Returns to the supplier stay out, same as the total: they are
        // replaced or refunded, not lost.
        $lossesByReason = StockWriteOff::where('pharmacy_id', $pharmacyId)
            ->counted()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('reason, SUM(quantity * unit_cost) as total_loss')
            ->groupBy('reason')
            ->pluck('total_loss', 'reason');

        $losses = (float) $lossesByReason->sum();

        // ✅ FIX: الرواتب نحسبها نسبة للفترة الزمنية مو كاملة
        $monthlySalaries = Employee::where('pharmacy_id', $pharmacyId)
            ->where('status', Employee::STATUS_APPROVED)
            ->where('role', Employee::ROLE_EMPLOYEE)
            ->sum('salary');

        $salaryForPeriod = match ($request->filter) {
            'daily' => $monthlySalaries / 30,
            'weekly' => $monthlySalaries / 4,
            'monthly' => $monthlySalaries,
            'yearly' => $monthlySalaries * 12,
        };

        // Both sides of a return come off. The money went back to the customer
        // and, unless the box came back broken, so did the stock — and when it
        // did come back broken the write-off above already carries that cost.
        // Reversing only the revenue would charge the pharmacy twice.
        $refunds = $this->refundsIn($pharmacyId, $start, $end);
        $returnedCost = $this->returnedCostIn($pharmacyId, $start, $end);

        $netRevenue = (float) $revenue - $refunds;
        $netCost = (float) ($costOfGoods->total_cost ?? 0) - $returnedCost;

        $profit = $netRevenue - $netCost - $salaryForPeriod - $losses;

        return response()->json([
            'filter' => $request->filter,
            'revenue' => round($netRevenue, 2),
            'refunds' => round($refunds, 2),
            'cost_of_goods' => round($netCost, 2),
            'salaries' => round($salaryForPeriod, 2),
            'write_offs' => round($losses, 2),
            // Same three reasons a pharmacist can pick when booking a write-off.
            // `returned_to_supplier` is not here on purpose: it is never a loss.
            'write_offs_by_reason' => [
                StockWriteOff::REASON_EXPIRED => round((float) ($lossesByReason[StockWriteOff::REASON_EXPIRED] ?? 0), 2),
                StockWriteOff::REASON_DAMAGED => round((float) ($lossesByReason[StockWriteOff::REASON_DAMAGED] ?? 0), 2),
                StockWriteOff::REASON_LOST => round((float) ($lossesByReason[StockWriteOff::REASON_LOST] ?? 0), 2),
            ],
            'profit' => round($profit, 2),
        ]);
    }

    /**
     * Money in and money out, which is not the same question as profit.
     *
     * Profit answers "did the shop trade well": revenue less what the goods
     * sold cost, less wages, less stock thrown away. Buying stock does not
     * appear in it at all, and correctly so — cash turned into inventory is not
     * a cost, it is the same value in a different form.
     *
     * Which is exactly why a pharmacy can be profitable and still unable to pay
     * anyone. Two million spent on a delivery leaves the profit figure
     * untouched and the till empty, and until now no screen in this application
     * would have shown that.
     *
     * A purchase counts on the day the order was placed, which is when a
     * Syrian wholesaler is paid. Waiting for the delivery to be marked received
     * would leave money already gone sitting in a report as though it were
     * still there.
     */
    public function getCashFlow(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $sales = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end]);

        // Split by method because they are not the same money. Cash is in the
        // drawer tonight; a card settles later and insurance later still.
        $byMethod = (clone $sales)
            ->selectRaw('payment_method, SUM(total_price) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        // A refund is money leaving the drawer, so it comes straight off what
        // came in rather than being listed as a cost of doing business.
        $moneyIn = (float) (clone $sales)->sum('total_price')
            - $this->refundsIn($pharmacyId, $start, $end);

        // Cancelled orders were never paid for, so they are not money out.
        $purchases = (float) Order::where('pharmacy_id', $pharmacyId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_price');

        $monthlySalaries = Employee::where('pharmacy_id', $pharmacyId)
            ->where('status', Employee::STATUS_APPROVED)
            ->where('role', Employee::ROLE_EMPLOYEE)
            ->sum('salary');

        $salaries = match ($request->filter) {
            'daily' => $monthlySalaries / 30,
            'weekly' => $monthlySalaries / 4,
            'monthly' => $monthlySalaries,
            'yearly' => $monthlySalaries * 12,
        };

        $moneyOut = $purchases + $salaries;

        return response()->json([
            'filter' => $request->filter,
            'money_in' => round($moneyIn, 2),
            'money_in_by_method' => [
                'cash' => round((float) ($byMethod['cash'] ?? 0), 2),
                'card' => round((float) ($byMethod['card'] ?? 0), 2),
                'insurance' => round((float) ($byMethod['insurance'] ?? 0), 2),
            ],
            'money_out' => round($moneyOut, 2),
            'purchases' => round($purchases, 2),
            'salaries' => round($salaries, 2),
            'net' => round($moneyIn - $moneyOut, 2),
        ]);
    }

    /**
     * How the shop's sales were paid for.
     *
     * Its own endpoint rather than a slice of the cash flow, because the
     * question is different: cash flow asks how much came in, this asks in what
     * form — and a pharmacy with a third of its takings tied up in insurance
     * claims has a problem that a single total hides.
     */
    public function getPaymentMethods(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $rows = Sale::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('payment_method, COUNT(*) as sales, SUM(total_price) as total')
            ->groupBy('payment_method')
            ->get();

        $total = (float) $rows->sum('total');

        return response()->json([
            'filter' => $request->filter,
            'total' => round($total, 2),
            'methods' => $rows->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'sales' => (int) $row->sales,
                'total' => round((float) $row->total, 2),
                // Rendered as a share of takings, so a donut does not have to
                // work it out and disagree with the figure beside it.
                'share' => $total > 0 ? round((float) $row->total / $total * 100, 1) : 0,
            ])->values()->all(),
        ]);
    }

    /** What was handed back to customers in this period. */
    private function refundsIn(int $pharmacyId, $start, $end): float
    {
        return (float) SaleReturn::where('pharmacy_id', $pharmacyId)
            ->whereBetween('created_at', [$start, $end])
            ->sum('refund_amount');
    }

    /**
     * What the returned goods had cost, so cost of goods can be undone too.
     *
     * Read off the sale line, which froze the cost of the exact boxes that left
     * the shop. Reversing at today's cost would leave a residue behind every
     * return that changed nothing.
     */
    private function returnedCostIn(int $pharmacyId, $start, $end): float
    {
        return (float) SaleReturn::where('sale_returns.pharmacy_id', $pharmacyId)
            ->whereBetween('sale_returns.created_at', [$start, $end])
            ->join('sale_items', 'sale_items.id', '=', 'sale_returns.sale_item_id')
            ->sum(DB::raw('sale_returns.quantity * sale_items.cost_price'));
    }

    // ===== الأدوية الأكثر مبيعاً =====
    public function getMostSoldMedicines(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $medicines = SaleItem::whereHas('sale', function ($query) use ($pharmacyId, $start, $end) {
            $query->where('pharmacy_id', $pharmacyId)
                ->whereBetween('created_at', [$start, $end]);
        })
            ->selectRaw('medicine_id, SUM(quantity) as total_sold, SUM(quantity * price) as revenue')
            ->groupBy('medicine_id')
            ->orderByDesc('revenue')
            ->with('medicine:id,name,category_medicine')
            ->get()
            // Grouped back up by name: a drug held in two batches is two rows
            // here, and a top-sellers list that shows the same drug twice is
            // reporting the shelf's filing system rather than the business.
            ->groupBy(fn ($item) => $item->medicine?->name ?? '')
            ->map(fn ($rows, $name) => [
                'medicine' => $name,
                'category' => $rows->first()->medicine?->category_medicine,
                'total_sold' => (int) $rows->sum('total_sold'),
                // Ranked by money, not by count. Selling four hundred boxes of
                // paracetamol matters less than the inhalers, and a list
                // ordered by units says the opposite.
                'revenue' => round((float) $rows->sum('revenue'), 2),
            ])
            ->sortByDesc('revenue')
            ->values();

        return response()->json([
            'filter' => $request->filter,
            'medicines' => $medicines,
        ]);
    }

    // ===== الأكثر مبيعاً بالفئة =====
    public function getMostSoldByCategory(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'filter' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        [$start, $end] = $this->getDateRange($request->filter);

        $categories = SaleItem::whereHas('sale', function ($query) use ($pharmacyId, $start, $end) {
            $query->where('pharmacy_id', $pharmacyId)
                ->whereBetween('created_at', [$start, $end]);
        })
            ->selectRaw('medicines.category_medicine, SUM(sale_items.quantity) as total_sold, SUM(sale_items.quantity * sale_items.price) as revenue')
            ->join('medicines', 'sale_items.medicine_id', '=', 'medicines.id')
            ->groupBy('medicines.category_medicine')
            // By money. Which shelf earns the most is a different question from
            // which shelf moves the most boxes, and it is the useful one.
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'category_medicine' => $row->category_medicine,
                'total_sold' => (int) $row->total_sold,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        return response()->json([
            'filter' => $request->filter,
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
        $today = now()->toDateString();
        $start = now()->startOfDay();
        $end = now()->endOfDay();

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
            'date' => $today,
            'today_sales_count' => $todaySalesCount,
            'today_revenue' => round($todayRevenue, 2),
            'today_profit' => round($todayProfit, 2),
            'expiring_count' => $expiringCount,
            'low_stock_count' => $lowStockCount,
        ]);
    }
}