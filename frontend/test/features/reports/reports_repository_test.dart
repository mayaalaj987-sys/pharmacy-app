import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/reports/data/reports_remote_data_source.dart';
import 'package:phamacy_managment/features/reports/data/reports_repository.dart';
import 'package:phamacy_managment/features/reports/presentation/cubit/reports_cubit.dart';
import 'package:phamacy_managment/features/reports/presentation/cubit/reports_state.dart';

void main() {
  test('cash flow keeps money in and money out apart', () async {
    // The two figures the screen leads with. Profit says the shop traded well;
    // this says whether there is anything in the drawer, and a delivery empties
    // one without touching the other.
    final cash = await ReportsRepository(
      FakeReportsApi(),
    ).fetchCashFlow('monthly');

    expect(cash.moneyIn, 40000.0);
    expect(cash.purchases, 2000000.0);
    expect(cash.net, -1960000.0);
    expect(cash.inByMethod['cash'], 30000.0);
    expect(cash.inByMethod['insurance'], 0.0);
  });

  test('payment shares arrive already worked out', () async {
    // Computed server-side so the donut cannot derive its own and disagree
    // with the figure printed beside it.
    final methods = await ReportsRepository(
      FakeReportsApi(),
    ).fetchPaymentMethods('monthly');

    expect(methods, hasLength(2));
    expect(methods.first.label, 'Cash');
    expect(methods.first.share, 75.0);
    expect(methods.last.share, 25.0);
  });

  test('the average sale is a basket, not a count of sales', () async {
    final average = await ReportsRepository(
      FakeReportsApi(),
    ).fetchAverageSales('monthly');

    expect(average.salesCount, 2);
    expect(average.averageSale, 20000.0);
  });

  test('categories carry what they earned', () async {
    // Which shelf earns most is a different question from which moves most
    // boxes, and it is the one worth asking.
    final categories = await ReportsRepository(
      FakeReportsApi(),
    ).fetchCategoryRevenue('monthly');

    expect(categories.first.category, 'Respiratory');
    expect(categories.first.revenue, 52000.0);
    expect(categories.first.totalSold, 2);
  });

  test('analytics loads every panel in one pass', () async {
    // Seven sequential round trips on a mobile connection is a screen that
    // looks hung, and none of these depends on another.
    final cubit = ReportsCubit(ReportsRepository(FakeReportsApi()));

    await cubit.loadAnalytics(filter: 'weekly');

    expect(cubit.state.analyticsStatus, ReportsStatus.ready);
    expect(cubit.state.cashFlow.net, -1960000.0);
    expect(cubit.state.paymentMethods, hasLength(2));
    expect(cubit.state.salesAverage.averageSale, 20000.0);
    expect(cubit.state.categoryRevenue, hasLength(2));
    // The period chart compares all four windows, so the screen has to load
    // them even when the dashboard was never opened.
    expect(cubit.state.revenueByFilter, hasLength(4));
    await cubit.close();
  });

  test('fetchDashboard parses today summary counts and money', () async {
    final summary = await ReportsRepository(FakeReportsApi()).fetchDashboard();

    expect(summary.date, '2026-08-16');
    expect(summary.todaySalesCount, 4);
    expect(summary.todayRevenue, 320.5);
    expect(summary.todayProfit, 120.25);
    expect(summary.expiringCount, 2);
    expect(summary.lowStockCount, 3);
  });

  test('fetchProfits parses the server-side profit breakdown', () async {
    final profits = await ReportsRepository(
      FakeReportsApi(),
    ).fetchProfits('monthly');

    expect(profits.filter, 'monthly');
    expect(profits.revenue, 1000.0);
    expect(profits.costOfGoods, 400.0);
    expect(profits.salaries, 200.0);
    // Stock thrown away rather than sold. It reduced profit at no point in its
    // life before this figure existed.
    expect(profits.writeOffs, 50.0);
    expect(profits.refunds, 25.0);
    // Profit comes from the backend; it is never recomputed client-side.
    expect(profits.profit, 400.0);
  });

  test('fetchInventoryValue parses cost and selling totals', () async {
    final value = await ReportsRepository(
      FakeReportsApi(),
    ).fetchInventoryValue();

    expect(value.totalCostValue, 5000.0);
    expect(value.totalSellingValue, 8000.0);
    // Broken out because the till refuses to sell it: a headline figure
    // without this says the pharmacy holds money it cannot get at.
    expect(value.expiredCostValue, 492.0);
    expect(value.expiringCostValue, 80.0);
  });

  test('fetchMostSold parses the medicines list', () async {
    final items = await ReportsRepository(
      FakeReportsApi(),
    ).fetchMostSold('monthly');

    expect(items, hasLength(2));
    expect(items.first.medicine, 'Augmentin');
    expect(items.first.category, 'Antibiotics');
    expect(items.first.totalSold, 30);
  });

  test('loadDashboard fetches revenue for every period', () async {
    final api = FakeReportsApi();
    final cubit = ReportsCubit(ReportsRepository(api));

    await cubit.loadDashboard();

    expect(cubit.state.status, ReportsStatus.ready);
    expect(api.revenueFilters, ReportsCubit.revenueFilters);
    expect(cubit.state.revenueByFilter['daily'], 100.0);
    expect(cubit.state.revenueByFilter['yearly'], 100.0);
    await cubit.close();
  });

  test('loadAnalytics stores the selected filter and results', () async {
    final cubit = ReportsCubit(ReportsRepository(FakeReportsApi()));

    await cubit.loadAnalytics(filter: 'weekly');

    expect(cubit.state.analyticsStatus, ReportsStatus.ready);
    expect(cubit.state.analyticsFilter, 'weekly');
    expect(cubit.state.mostSold, hasLength(2));
    expect(cubit.state.inventoryValue.totalCostValue, 5000.0);
    await cubit.close();
  });

  test('a dashboard failure surfaces an error state', () async {
    final cubit = ReportsCubit(
      ReportsRepository(FakeReportsApi()..fail = true),
    );

    await cubit.loadDashboard();

    expect(cubit.state.status, ReportsStatus.failure);
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });

  test('an analytics failure surfaces a separate error state', () async {
    final cubit = ReportsCubit(
      ReportsRepository(FakeReportsApi()..fail = true),
    );

    await cubit.loadAnalytics();

    expect(cubit.state.analyticsStatus, ReportsStatus.failure);
    expect(cubit.state.analyticsError, isNotNull);
    await cubit.close();
  });
}

class FakeReportsApi implements ReportsRemoteDataSource {
  @override
  Future<Response<dynamic>> getMostSoldByCategory(String filter) async {
    return _json('/reports/most-sold-category', {
      'filter': filter,
      'categories': [
        {'category_medicine': 'Respiratory', 'total_sold': 2, 'revenue': 52000},
        {
          'category_medicine': 'Painkillers',
          'total_sold': 20,
          'revenue': 20000,
        },
      ],
    });
  }

  @override
  Future<Response<dynamic>> getAverageSales(String filter) async {
    return _json('/reports/average-sales', {
      'filter': filter,
      'sales_count': 2,
      'total': 40000,
      'average_sale': 20000,
    });
  }

  @override
  Future<Response<dynamic>> getCashFlow(String filter) async {
    return _json('/reports/cash-flow', {
      'filter': filter,
      'money_in': 40000,
      'money_in_by_method': {'cash': 30000, 'card': 10000, 'insurance': 0},
      'money_out': 2000000,
      'purchases': 2000000,
      'salaries': 0,
      'net': -1960000,
    });
  }

  @override
  Future<Response<dynamic>> getPaymentMethods(String filter) async {
    return _json('/reports/payment-methods', {
      'filter': filter,
      'total': 40000,
      'methods': [
        {'payment_method': 'cash', 'sales': 1, 'total': 30000, 'share': 75.0},
        {'payment_method': 'card', 'sales': 1, 'total': 10000, 'share': 25.0},
      ],
    });
  }

  Response<dynamic> _json(String path, Map<String, dynamic> body) {
    return Response<dynamic>(
      requestOptions: RequestOptions(path: path),
      data: body,
    );
  }

  bool fail = false;
  final List<String> revenueFilters = [];

  DioException _error(String path) => DioException(
    requestOptions: RequestOptions(path: path),
    response: Response<dynamic>(
      requestOptions: RequestOptions(path: path),
      statusCode: 500,
      data: {'message': 'Server error'},
    ),
    type: DioExceptionType.badResponse,
  );

  @override
  Future<Response<dynamic>> getDashboard() async {
    if (fail) throw _error('/reports/dashboard');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/dashboard'),
      data: {
        'date': '2026-08-16',
        'today_sales_count': 4,
        'today_revenue': 320.5,
        'today_profit': 120.25,
        'expiring_count': 2,
        'low_stock_count': 3,
      },
    );
  }

  @override
  Future<Response<dynamic>> getRevenue(String filter) async {
    if (fail) throw _error('/reports/revenue');
    revenueFilters.add(filter);
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/revenue'),
      data: {'filter': filter, 'revenue': '100.00'},
    );
  }

  @override
  Future<Response<dynamic>> getProfits(String filter) async {
    if (fail) throw _error('/reports/profits');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/profits'),
      data: {
        'filter': filter,
        'revenue': 1000,
        'cost_of_goods': '400.00',
        'salaries': 200,
        'write_offs': 50,
        'refunds': 25,
        'profit': 400,
      },
    );
  }

  @override
  Future<Response<dynamic>> getInventoryValue() async {
    if (fail) throw _error('/reports/inventory-value');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/inventory-value'),
      data: {
        'total_cost_value': '5000.00',
        'total_selling_value': 8000,
        'expired_cost_value': 492,
        'expiring_cost_value': 80,
      },
    );
  }

  @override
  Future<Response<dynamic>> getMostSold(String filter) async {
    if (fail) throw _error('/reports/most-sold');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/most-sold'),
      data: {
        'filter': filter,
        'medicines': [
          {
            'medicine': 'Augmentin',
            'category': 'Antibiotics',
            'total_sold': '30',
          },
          {'medicine': 'Panadol', 'category': 'Painkillers', 'total_sold': 12},
        ],
      },
    );
  }
}
