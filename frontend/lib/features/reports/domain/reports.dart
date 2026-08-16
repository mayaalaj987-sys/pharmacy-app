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
  final double profit;

  const ProfitReport({
    required this.filter,
    required this.revenue,
    required this.costOfGoods,
    required this.salaries,
    required this.profit,
  });

  static const empty = ProfitReport(
    filter: '',
    revenue: 0,
    costOfGoods: 0,
    salaries: 0,
    profit: 0,
  );

  factory ProfitReport.fromJson(Map<String, dynamic> json) {
    return ProfitReport(
      filter: json['filter']?.toString() ?? '',
      revenue: _toDouble(json['revenue']),
      costOfGoods: _toDouble(json['cost_of_goods']),
      salaries: _toDouble(json['salaries']),
      profit: _toDouble(json['profit']),
    );
  }
}

/// `GET /reports/inventory-value`
class InventoryValue {
  final double totalCostValue;
  final double totalSellingValue;

  const InventoryValue({
    required this.totalCostValue,
    required this.totalSellingValue,
  });

  static const empty = InventoryValue(totalCostValue: 0, totalSellingValue: 0);

  factory InventoryValue.fromJson(Map<String, dynamic> json) {
    return InventoryValue(
      totalCostValue: _toDouble(json['total_cost_value']),
      totalSellingValue: _toDouble(json['total_selling_value']),
    );
  }
}

/// One row of `GET /reports/most-sold?filter=...`
class MostSoldMedicine {
  final String medicine;
  final String category;
  final int totalSold;

  const MostSoldMedicine({
    required this.medicine,
    required this.category,
    required this.totalSold,
  });

  factory MostSoldMedicine.fromJson(Map<String, dynamic> json) {
    return MostSoldMedicine(
      medicine: json['medicine']?.toString() ?? '-',
      category: json['category']?.toString() ?? '-',
      totalSold: _toInt(json['total_sold']),
    );
  }
}
