import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/sales/data/sales_remote_data_source.dart';
import 'package:phamacy_managment/features/sales/data/sales_repository.dart';
import 'package:phamacy_managment/features/sales/presentation/cubit/sales_cubit.dart';
import 'package:phamacy_managment/features/sales/presentation/cubit/sales_state.dart';

void main() {
  test('fetchAllSales parses totals, items and payment method', () async {
    final summary = await SalesRepository(FakeSalesApi()).fetchAllSales();

    expect(summary.totalSales, 2);
    expect(summary.totalPrice, 160.0);
    expect(summary.sales, hasLength(2));

    final first = summary.sales.first;
    expect(first.id, 1);
    expect(first.customerName, 'Sam');
    expect(first.paymentMethod, 'insurance');
    expect(first.paymentLabel, 'Insurance');
    expect(first.totalPrice, 80.0);
    expect(first.totalQuantity, 3);
    expect(first.items.first.medicineName, 'Augmentin');
    expect(first.items.first.lineTotal, 40.0);

    // Empty customer_name normalises to null so the UI shows "Walk In Customer".
    expect(summary.sales.last.customerName, isNull);
  });

  test('fetchDailySales parses the daily envelope', () async {
    final summary = await SalesRepository(FakeSalesApi()).fetchDailySales();

    expect(summary.totalSales, 1);
    expect(summary.totalPrice, 80.0);
    expect(summary.sales, hasLength(1));
  });

  test('createSale sends only backend fields and never actor ids', () async {
    final api = FakeSalesApi();

    await SalesRepository(api).createSale(
      items: [
        {'medicine_id': 11, 'quantity': 2},
      ],
      paymentMethod: 'cash',
      customerName: '  Sam  ',
    );

    final payload = api.lastSalePayload!;
    expect(payload['payment_method'], 'cash');
    expect(payload['items'], [
      {'medicine_id': 11, 'quantity': 2},
    ]);
    expect(payload['customer_name'], 'Sam');
    // The backend rejects client-supplied actor ids.
    expect(payload.containsKey('pharmacist_id'), isFalse);
    expect(payload.containsKey('employee_id'), isFalse);
    // card_number is only sent for card payments.
    expect(payload.containsKey('card_number'), isFalse);
  });

  test('createSale includes card_number only for card payments', () async {
    final api = FakeSalesApi();

    await SalesRepository(api).createSale(
      items: [
        {'medicine_id': 11, 'quantity': 1},
      ],
      paymentMethod: 'card',
      cardNumber: '1234567890',
    );

    expect(api.lastSalePayload!['card_number'], '1234567890');
  });

  test('an out-of-stock rejection surfaces the backend message', () async {
    final cubit = SalesCubit(SalesRepository(FakeSalesApi()..failSale = true));

    final ok = await cubit.createSale(
      items: [
        {'medicine_id': 11, 'quantity': 999},
      ],
      paymentMethod: 'cash',
    );

    expect(ok, isFalse);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.submitting, isFalse);
    await cubit.close();
  });

  test('a successful sale refetches authoritative sales state', () async {
    final api = FakeSalesApi();
    final cubit = SalesCubit(SalesRepository(api));

    final ok = await cubit.createSale(
      items: [
        {'medicine_id': 11, 'quantity': 1},
      ],
      paymentMethod: 'cash',
    );

    expect(ok, isTrue);
    expect(api.listCalls, 1);
    expect(cubit.state.status, SalesStatus.ready);
    expect(cubit.state.sales, hasLength(2));
    await cubit.close();
  });
}

class FakeSalesApi implements SalesRemoteDataSource {
  Map<String, dynamic>? lastSalePayload;
  bool failSale = false;
  int listCalls = 0;

  @override
  Future<Response<dynamic>> createSale(Map<String, dynamic> data) async {
    lastSalePayload = data;
    if (failSale) {
      throw DioException(
        requestOptions: RequestOptions(path: '/sale/create'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/sale/create'),
          statusCode: 400,
          data: {'message': 'الكمية غير متوفرة: Augmentin'},
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/sale/create'),
      statusCode: 201,
      data: {
        'sale_id': 3,
        'total_price': 20,
        'items_count': 1,
        'payment_method': data['payment_method'],
        'date': '2026-08-16',
      },
    );
  }

  @override
  Future<Response<dynamic>> getAllSales({String? filter}) async {
    listCalls++;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/sale/all'),
      data: {
        'total_sales': 2,
        'total_price': 160,
        'sales': [
          {
            'id': 1,
            'customer_name': 'Sam',
            'payment_method': 'insurance',
            'total_price': '80.00',
            'date': '2026-08-16',
            'items': [
              {
                'id': 1,
                'quantity': 2,
                'price': '20.00',
                'medicine': {'id': 11, 'name': 'Augmentin'},
              },
              {
                'id': 2,
                'quantity': 1,
                'price': '40.00',
                'medicine': {'id': 12, 'name': 'Panadol'},
              },
            ],
          },
          {
            'id': 2,
            'customer_name': '',
            'payment_method': 'cash',
            'total_price': 80,
            'date': '2026-08-16',
            'items': const [],
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> getDailySales() async {
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/sale/daily'),
      data: {
        'date': '2026-08-16',
        'total_sales': 1,
        'total_price': 80,
        'sales': [
          {
            'id': 1,
            'customer_name': 'Sam',
            'payment_method': 'cash',
            'total_price': 80,
            'date': '2026-08-16',
            'items': const [],
          },
        ],
      },
    );
  }
}
