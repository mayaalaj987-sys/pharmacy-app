import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/orders/data/orders_remote_data_source.dart';
import 'package:phamacy_managment/features/orders/data/orders_repository.dart';
import 'package:phamacy_managment/features/orders/presentation/cubit/orders_cubit.dart';
import 'package:phamacy_managment/features/orders/presentation/cubit/orders_state.dart';

void main() {
  test('fetchOrders parses supplier, items and lowercase status', () async {
    final orders = await OrdersRepository(FakeOrdersApi()).fetchOrders();

    expect(orders, hasLength(2));
    final pending = orders.first;
    expect(pending.id, 1);
    expect(pending.supplierName, 'Medical Pharma');
    expect(pending.status, 'pending');
    expect(pending.statusLabel, 'Pending');
    expect(pending.isPending, isTrue);
    expect(pending.totalPrice, 240.0);
    expect(pending.totalQuantity, 70);
    expect(pending.medicinesSummary, 'Augmentin +1 more');

    final received = orders.last;
    expect(received.statusLabel, 'Received');
    expect(received.isPending, isFalse);
    expect(received.medicinesSummary, 'Panadol');
  });

  test('createOrder posts the backend contract shape', () async {
    final api = FakeOrdersApi();

    await OrdersRepository(api).createOrder(
      supplierId: 3,
      items: [
        {'medicine_id': 11, 'quantity': 50},
      ],
    );

    expect(api.lastCreatePayload!['supplier_id'], 3);
    expect(api.lastCreatePayload!['payment_method'], 'cash');
    expect(api.lastCreatePayload!['items'], [
      {'medicine_id': 11, 'quantity': 50},
    ]);
  });

  test('cubit load exposes ready state and status counts', () async {
    final cubit = OrdersCubit(OrdersRepository(FakeOrdersApi()));

    await cubit.load();

    expect(cubit.state.status, OrdersStatus.ready);
    expect(cubit.state.orders, hasLength(2));
    expect(cubit.state.countByStatus('pending'), 1);
    expect(cubit.state.countByStatus('received'), 1);
    expect(cubit.state.countByStatus('cancelled'), 0);
    await cubit.close();
  });

  test('receive refetches server state and clears the mutating flag', () async {
    final api = FakeOrdersApi();
    final cubit = OrdersCubit(OrdersRepository(api));

    final ok = await cubit.receiveOrder(1);

    expect(ok, isTrue);
    expect(api.receivedIds, [1]);
    expect(api.listCalls, 1);
    expect(cubit.state.mutatingOrderId, isNull);
    await cubit.close();
  });

  test('the requested quantity is sent, not a hardcoded one', () async {
    final api = FakeOrdersApi();
    final cubit = OrdersCubit(OrdersRepository(api));

    await cubit.createOrder(supplierId: 3, medicineId: 11, quantity: 7);

    final items = api.lastCreatePayload!['items'] as List;
    expect(items.single, {'medicine_id': 11, 'quantity': 7});
    await cubit.close();
  });

  test(
    'a supplier stock rejection surfaces the real available figure',
    () async {
      // The catalogue is shared, so the figure the screen showed can be stale by
      // the time the order lands. The backend answers with the truth.
      final api = FakeOrdersApi()..supplierOutOfStock = true;
      final cubit = OrdersCubit(OrdersRepository(api));

      final ok = await cubit.createOrder(
        supplierId: 3,
        medicineId: 11,
        quantity: 20,
      );

      expect(ok, isFalse);
      expect(cubit.state.error, isNotNull);
      expect(cubit.state.error!.code, 'supplier_stock_insufficient');
      expect(cubit.state.error!.message, contains('Only 5 units'));
      await cubit.close();
    },
  );

  test('a rejected cancel surfaces the backend message', () async {
    final api = FakeOrdersApi()..failMutation = true;
    final cubit = OrdersCubit(OrdersRepository(api));

    final ok = await cubit.cancelOrder(2);

    expect(ok, isFalse);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.mutatingOrderId, isNull);
    await cubit.close();
  });
}

class FakeOrdersApi implements OrdersRemoteDataSource {
  Map<String, dynamic>? lastCreatePayload;
  final List<int> receivedIds = [];
  bool failMutation = false;
  int listCalls = 0;
  bool supplierOutOfStock = false;

  @override
  Future<Response<dynamic>> getOrders() async {
    listCalls++;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/orders'),
      data: {
        'orders': [
          {
            'id': 1,
            'status': 'pending',
            'total_price': '240.00',
            'payment_method': 'cash',
            'date': '2026-08-16',
            'supplier': {'id': 3, 'name': 'Medical Pharma'},
            'items': [
              {
                'id': 1,
                'quantity': 50,
                'price': '4.00',
                'medicine': {'id': 11, 'name': 'Augmentin'},
              },
              {
                'id': 2,
                'quantity': 20,
                'price': '2.00',
                'medicine': {'id': 12, 'name': 'Panadol'},
              },
            ],
          },
          {
            'id': 2,
            'status': 'received',
            'total_price': 100,
            'payment_method': 'card',
            'supplier': {'id': 3, 'name': 'Medical Pharma'},
            'items': [
              {
                'id': 3,
                'quantity': 10,
                'price': 10,
                'medicine': {'id': 12, 'name': 'Panadol'},
              },
            ],
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> createOrder(Map<String, dynamic> data) async {
    lastCreatePayload = data;
    if (supplierOutOfStock) {
      throw DioException(
        requestOptions: RequestOptions(path: '/orders'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/orders'),
          statusCode: 400,
          data: {
            'message':
                'Only 5 units of Amoxicillin 500mg are available from '
                'this supplier.',
            'code': 'supplier_stock_insufficient',
            'medicine': {
              'id': 11,
              'name': 'Amoxicillin 500mg',
              'available_quantity': 5,
              'requested_quantity': 20,
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/orders'),
      statusCode: 201,
      data: {'order_id': 9, 'total_price': 200, 'status': 'pending'},
    );
  }

  @override
  Future<Response<dynamic>> receiveOrder(int id) async {
    if (failMutation) throw _error('/orders/$id/receive');
    receivedIds.add(id);
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/orders/$id/receive'),
      data: {'message': 'ok'},
    );
  }

  @override
  Future<Response<dynamic>> cancelOrder(int id) async {
    if (failMutation) throw _error('/orders/$id/cancel');
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/orders/$id/cancel'),
      data: {'message': 'ok'},
    );
  }

  DioException _error(String path) => DioException(
    requestOptions: RequestOptions(path: path),
    response: Response<dynamic>(
      requestOptions: RequestOptions(path: path),
      statusCode: 400,
      data: {'message': 'Order cannot be changed in its current state.'},
    ),
    type: DioExceptionType.badResponse,
  );
}
