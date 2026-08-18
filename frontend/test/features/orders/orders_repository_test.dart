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

  test('the receiving plan says which drugs are new to the pharmacy', () async {
    // What the sheet needs in order to ask for a price only where one is
    // genuinely missing.
    final plan = await OrdersCubit(
      OrdersRepository(FakeOrdersApi()),
    ).receivingPlan(1);

    expect(plan, isNotNull);
    expect(plan!.supplierName, 'Medical Pharma');
    expect(plan.newCount, 1);

    final known = plan.items.first;
    expect(known.isNew, isFalse);
    expect(known.currentSellingPrice, 9000.0);
    // Their own price is the suggestion; there is nothing to decide.
    expect(known.suggestedSellingPrice, 9000.0);
    // What was paid on this order, not the catalogue's price today.
    expect(known.unitCost, 7000.0);

    final fresh = plan.items.last;
    expect(fresh.isNew, isTrue);
    expect(fresh.currentSellingPrice, isNull);
    expect(fresh.suggestedSellingPrice, 22000.0);
  });

  test('chosen shelf prices are sent keyed by medicine id', () async {
    final api = FakeOrdersApi();
    final cubit = OrdersCubit(OrdersRepository(api));

    await cubit.receiveOrder(1, sellingPrices: {11: 13000, 12: 25000});

    expect(api.lastReceivePayload!['selling_prices'], {
      '11': 13000.0,
      '12': 25000.0,
    });
    await cubit.close();
  });

  test('receiving without prices sends none, leaving them alone', () async {
    // A restock is not a reason to revisit a margin the pharmacy already set.
    final api = FakeOrdersApi();
    final cubit = OrdersCubit(OrdersRepository(api));

    await cubit.receiveOrder(1);

    expect(api.lastReceivePayload!.containsKey('selling_prices'), isFalse);
    await cubit.close();
  });

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
  Map<String, dynamic>? lastReceivePayload;
  final List<int> receivedIds = [];
  bool failMutation = false;
  int listCalls = 0;

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
  Future<Response<dynamic>> getReceivingPlan(int id) async {
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/orders/$id/receiving-plan'),
      data: {
        'order': {
          'id': id,
          'supplier_name': 'Medical Pharma',
          'total_price': 240,
          'payment_method': 'cash',
        },
        'items': [
          {
            'medicine_id': 11,
            'name': 'Amoxicillin 500mg',
            'quantity': 10,
            'unit_cost': 7000,
            'is_new': false,
            'current_selling_price': 9000,
            'suggested_selling_price': 9000,
          },
          {
            'medicine_id': 12,
            'name': 'Cefixime 400mg',
            'quantity': 10,
            'unit_cost': 15000,
            'is_new': true,
            'current_selling_price': null,
            'suggested_selling_price': 22000,
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> receiveOrder(
    int id,
    Map<String, dynamic> data,
  ) async {
    if (failMutation) throw _error('/orders/$id/receive');
    lastReceivePayload = data;
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
