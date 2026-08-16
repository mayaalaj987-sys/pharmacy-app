import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/reports/data/reports_remote_data_source.dart';
import 'package:phamacy_managment/features/reports/data/reports_repository.dart';
import 'package:phamacy_managment/features/reports/presentation/cubit/reports_cubit.dart';
import 'package:phamacy_managment/features/reports/presentation/cubit/reports_state.dart';

void main() {
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
    final profits =
        await ReportsRepository(FakeReportsApi()).fetchProfits('monthly');

    expect(profits.filter, 'monthly');
    expect(profits.revenue, 1000.0);
    expect(profits.costOfGoods, 400.0);
    expect(profits.salaries, 200.0);
    // Profit comes from the backend; it is never recomputed client-side.
    expect(profits.profit, 400.0);
  });

  test('fetchInventoryValue parses cost and selling totals', () async {
    final value =
        await ReportsRepository(FakeReportsApi()).fetchInventoryValue();

    expect(value.totalCostValue, 5000.0);
    expect(value.totalSellingValue, 8000.0);
  });

  test('fetchMostSold parses the medicines list', () async {
    final items =
        await ReportsRepository(FakeReportsApi()).fetchMostSold('monthly');

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
        'profit': 400,
      },
    );
  }

  @override
  Future<Response<dynamic>> getInventoryValue() async {
    if (fail) throw _error('/reports/inventory-value');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/reports/inventory-value'),
      data: {'total_cost_value': '5000.00', 'total_selling_value': 8000},
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
