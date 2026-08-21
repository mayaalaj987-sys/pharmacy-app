double _toDouble(dynamic v) =>
    v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

int _toInt(dynamic v) =>
    v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

/// `GET /reports/dashboard`
class DashboardSummary {
  final String date;
  final int todaySalesCount;
  final double todayRevenue;
  final double todayProfit;
  final int expiringCount;
  final int lowStockCount;

  const DashboardSummary({
    required this.date,
    required this.todaySalesCount,
    required this.todayRevenue,
    required this.todayProfit,
    required this.expiringCount,
    required this.lowStockCount,
  });

  static const empty = DashboardSummary(
    date: '',
    todaySalesCount: 0,
    todayRevenue: 0,
    todayProfit: 0,
    expiringCount: 0,
    lowStockCount: 0,
  );

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    return DashboardSummary(
      date: json['date']?.toString() ?? '',
      todaySalesCount: _toInt(json['today_sales_count']),
      todayRevenue: _toDouble(json['today_revenue']),
      todayProfit: _toDouble(json['today_profit']),
      expiringCount: _toInt(json['expiring_count']),
      lowStockCount: _toInt(json['low_stock_count']),
    );
  }
}

/// `GET /reports/revenue?filter=...`
class RevenuePoint {
  final String filter;
  final double revenue;

  const RevenuePoint({required this.filter, required this.revenue});

  factory RevenuePoint.fromJson(Map<String, dynamic> json) {
    return RevenuePoint(
      filter: json['filter']?.toString() ?? '',
      revenue: _toDouble(json['revenue']),
    );
  }
}

/// `GET /reports/profits?filter=...`
class ProfitReport {
  final String filter;
  final double revenue;
  final double costOfGoods;
  final double salaries;

  /// Stock thrown away rather than sold. Before this existed it reduced profit
  /// at no point in its life: not a cost when bought, not a cost when binned.
  final double writeOffs;

  /// The total above, split by why: expired, damaged, or lost at a stock
  /// count. Three different problems with three different fixes, which a
  /// single figure cannot tell apart. Returns to the supplier are not a
  /// reason here — they were never a loss, so the server never counts them.
  final Map<String, double> writeOffsByReason;

  /// Money handed back to customers, booked in the period it happened.
  final double refunds;

  final double profit;

  const ProfitReport({
    required this.filter,
    required this.revenue,
    required this.costOfGoods,
    required this.salaries,
    required this.profit,
    this.writeOffs = 0,
    this.writeOffsByReason = const <String, double>{},
    this.refunds = 0,
  });

  static const empty = ProfitReport(
    filter: '',
    revenue: 0,
    costOfGoods: 0,
    salaries: 0,
    profit: 0,
  );

  factory ProfitReport.fromJson(Map<String, dynamic> json) {
    final byReason = json['write_offs_by_reason'];

    return ProfitReport(
      filter: json['filter']?.toString() ?? '',
      revenue: _toDouble(json['revenue']),
      costOfGoods: _toDouble(json['cost_of_goods']),
      salaries: _toDouble(json['salaries']),
      writeOffs: _toDouble(json['write_offs']),
      writeOffsByReason: byReason is Map<String, dynamic>
          ? byReason.map((key, value) => MapEntry(key, _toDouble(value)))
          : const <String, double>{},
      refunds: _toDouble(json['refunds']),
      profit: _toDouble(json['profit']),
    );
  }
}

/// `GET /reports/cash-flow?filter=...`
///
/// The question profit does not answer. Buying stock never touches profit —
/// cash turned into inventory is the same value in another form — which is
/// exactly how a pharmacy trades well and still cannot pay anyone.
class CashFlow {
  final double moneyIn;
  final double moneyOut;
  final double purchases;
  final double salaries;
  final double net;

  /// What came in, split by how it was paid. Cash is in the drawer tonight; a
  /// card settles later and an insurance claim later still.
  final Map<String, double> inByMethod;

  const CashFlow({
    required this.moneyIn,
    required this.moneyOut,
    required this.purchases,
    required this.salaries,
    required this.net,
    this.inByMethod = const <String, double>{},
  });

  static const empty = CashFlow(
    moneyIn: 0,
    moneyOut: 0,
    purchases: 0,
    salaries: 0,
    net: 0,
  );

  factory CashFlow.fromJson(Map<String, dynamic> json) {
    final byMethod = json['money_in_by_method'];

    return CashFlow(
      moneyIn: _toDouble(json['money_in']),
      moneyOut: _toDouble(json['money_out']),
      purchases: _toDouble(json['purchases']),
      salaries: _toDouble(json['salaries']),
      net: _toDouble(json['net']),
      inByMethod: byMethod is Map<String, dynamic>
          ? byMethod.map((key, value) => MapEntry(key, _toDouble(value)))
          : const <String, double>{},
    );
  }
}

/// `GET /reports/average-sales?filter=...`
class SalesAverage {
  final int salesCount;
  final double total;
  final double averageSale;

  const SalesAverage({
    required this.salesCount,
    required this.total,
    required this.averageSale,
  });

  static const empty = SalesAverage(salesCount: 0, total: 0, averageSale: 0);

  factory SalesAverage.fromJson(Map<String, dynamic> json) {
    return SalesAverage(
      salesCount: _toInt(json['sales_count']),
      total: _toDouble(json['total']),
      averageSale: _toDouble(json['average_sale']),
    );
  }
}

/// One slice of `GET /reports/payment-methods?filter=...`
///
/// The share arrives already worked out, so the donut cannot compute its own
/// and disagree with the figure printed beside it.
class PaymentMethodShare {
  final String method;
  final int sales;
  final double total;
  final double share;

  const PaymentMethodShare({
    required this.method,
    required this.sales,
    required this.total,
    required this.share,
  });

  String get label => switch (method) {
    'cash' => 'Cash',
    'card' => 'Card',
    'insurance' => 'Insurance',
    _ => method,
  };

  factory PaymentMethodShare.fromJson(Map<String, dynamic> json) {
    return PaymentMethodShare(
      method: json['payment_method']?.toString() ?? '',
      sales: _toInt(json['sales']),
      total: _toDouble(json['total']),
      share: _toDouble(json['share']),
    );
  }
}

/// `GET /reports/inventory-value`
class InventoryValue {
  final double totalCostValue;
  final double totalSellingValue;

  /// The part of the total that cannot be sold. The till refuses it, so a
  /// headline figure without this says the pharmacy holds money it does not.
  final double expiredCostValue;

  /// Still sellable, but on a clock.
  final double expiringCostValue;

  const InventoryValue({
    required this.totalCostValue,
    required this.totalSellingValue,
    this.expiredCostValue = 0,
    this.expiringCostValue = 0,
  });

  static const empty = InventoryValue(totalCostValue: 0, totalSellingValue: 0);

  factory InventoryValue.fromJson(Map<String, dynamic> json) {
    return InventoryValue(
      totalCostValue: _toDouble(json['total_cost_value']),
      totalSellingValue: _toDouble(json['total_selling_value']),
      expiredCostValue: _toDouble(json['expired_cost_value']),
      expiringCostValue: _toDouble(json['expiring_cost_value']),
    );
  }
}

/// One row of `GET /reports/most-sold?filter=...`
class MostSoldMedicine {
  final String medicine;
  final String category;
  final int totalSold;

  /// What it earned. The list ranks by this rather than by units, because four
  /// hundred boxes of paracetamol matter less than a handful of inhalers.
  final double revenue;

  const MostSoldMedicine({
    required this.medicine,
    required this.category,
    required this.totalSold,
    this.revenue = 0,
  });

  factory MostSoldMedicine.fromJson(Map<String, dynamic> json) {
    return MostSoldMedicine(
      medicine: json['medicine']?.toString() ?? '-',
      category: json['category']?.toString() ?? '-',
      totalSold: _toInt(json['total_sold']),
      revenue: _toDouble(json['revenue']),
    );
  }
}

/// One row of `GET /reports/most-sold-category?filter=...`
class CategoryRevenue {
  final String category;
  final int totalSold;
  final double revenue;

  const CategoryRevenue({
    required this.category,
    required this.totalSold,
    required this.revenue,
  });

  factory CategoryRevenue.fromJson(Map<String, dynamic> json) {
    return CategoryRevenue(
      category: json['category_medicine']?.toString() ?? '-',
      totalSold: _toInt(json['total_sold']),
      revenue: _toDouble(json['revenue']),
    );
  }
}
