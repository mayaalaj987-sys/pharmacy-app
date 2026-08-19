import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/purchase_cart/data/purchase_cart_remote_data_source.dart';
import 'package:phamacy_managment/features/purchase_cart/data/purchase_cart_repository.dart';
import 'package:phamacy_managment/features/purchase_cart/presentation/cubit/purchase_cart_cubit.dart';
import 'package:phamacy_managment/features/purchase_cart/presentation/cubit/purchase_cart_state.dart';

void main() {
  test('the cart parses its supplier groups and the totals', () async {
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();

    expect(cart.itemCount, 3);
    expect(cart.total, 126000);
    expect(cart.groups, hasLength(2));

    final barada = cart.groups.first;
    expect(barada.supplierName, 'Barada Pharma Distribution');
    expect(barada.subtotal, 90000);
    expect(barada.items, hasLength(2));
  });

  test('a line knows the unit cost it will actually be billed at', () async {
    // Not the suggested retail. Showing that on a buying screen tells the
    // pharmacist a number they will never be charged.
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();
    final line = cart.groups.first.items.first;

    expect(line.name, 'Amoxicillin 500mg');
    expect(line.unitCost, 8000);
    expect(line.quantity, 10);
    expect(line.subtotal, 80000);
  });

  test('a cheaper supplier is surfaced with the saving', () async {
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();
    final cheaper = cart.groups.first.items.first.cheaperElsewhere;

    expect(cheaper, isNotNull);
    expect(cheaper!.supplierName, 'Al-Shahba Pharmaceutical Trading');
    expect(cheaper.unitCost, 7000);
    expect(cheaper.saving, 10000);
    expect(cheaper.medicineId, 44);
  });

  test('a line already at the best price offers no switch', () async {
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();

    expect(cart.groups.first.items.last.cheaperElsewhere, isNull);
  });

  test('lines the app queued are marked as suggestions', () async {
    // They have to be recognisable, or the pharmacist buys stock they never
    // chose.
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();

    expect(cart.suggestedCount, 1);
    expect(cart.groups.last.items.single.suggested, isTrue);
    expect(cart.groups.first.items.first.suggested, isFalse);
  });

  test(
    'a line the supplier can no longer fill is flagged, not dropped',
    () async {
      final cart = await PurchaseCartRepository(FakeCartApi()).fetch();
      final short = cart.groups.last.items.single;

      expect(cart.unavailableCount, 1);
      expect(short.available, isFalse);
      expect(short.availableQuantity, 1);
    },
  );

  test('every mutation takes the whole cart the server answers with', () async {
    // Quantities interact — totals and the cheaper-elsewhere verdict both
    // depend on more than one row — so patching locally would show a cart that
    // never existed.
    final api = FakeCartApi();
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    await cubit.add(12, 5);

    expect(api.lastAdd, {'medicine_id': 12, 'quantity': 5});
    expect(cubit.state.cart.itemCount, 3);
    expect(cubit.state.status, PurchaseCartStatus.ready);
    expect(cubit.state.mutatingItemId, isNull);
    await cubit.close();
  });

  test('switching supplier posts the target offer', () async {
    final api = FakeCartApi();
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    await cubit.switchSupplier(1, 44);

    expect(api.lastSwitch, [1, 44]);
    await cubit.close();
  });

  test('checkout reports how many orders the cart became', () async {
    // One per supplier: the split is real and the pharmacist should see it.
    final cubit = PurchaseCartCubit(PurchaseCartRepository(FakeCartApi()));

    final placed = await cubit.checkout('card');

    expect(placed, 2);
    expect(cubit.state.mutatingItemId, isNull);
    await cubit.close();
  });

  test('a supplier running short leaves the cart alone and says so', () async {
    // All or nothing on the server, so the pharmacist lowers one line and
    // tries again rather than working out what went through.
    final api = FakeCartApi()..supplierShort = true;
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    final placed = await cubit.checkout('cash');

    expect(placed, isNull);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.error!.code, 'supplier_stock_insufficient');
    expect(cubit.state.error!.message, contains('Only 4 units'));
    expect(cubit.state.mutatingItemId, isNull);
    await cubit.close();
  });

  test('expiry is carried through to the line', () async {
    // Checkout refuses an expired line outright, so the screen has to be able
    // to say so before Buy is pressed rather than after it is rejected.
    final cart = await PurchaseCartRepository(FakeCartApi()).fetch();

    expect(cart.expiredCount, 1);
    expect(cart.expiringSoonCount, 1);

    final stale = cart.groups.first.items.last;
    expect(stale.expired, isTrue);
    expect(stale.expiresOn, '2026-01-01');

    final soon = cart.groups.last.items.single;
    expect(soon.expired, isFalse);
    expect(soon.expiringSoon, isTrue);
  });

  test('a card payment carries the card number', () async {
    // The server demands it exactly as the till does from a customer, and the
    // number is validated and discarded at both ends.
    final api = FakeCartApi();
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    await cubit.checkout('card', cardNumber: '1234567890');

    expect(api.lastCardNumber, '1234567890');
    await cubit.close();
  });

  test('a cash payment sends no card number at all', () async {
    final api = FakeCartApi();
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    await cubit.checkout('cash');

    expect(api.lastCardNumber, isNull);
    await cubit.close();
  });

  test('one write at a time', () async {
    // Two overlapping quantity changes would race, and the loser would win.
    final api = FakeCartApi();
    final cubit = PurchaseCartCubit(PurchaseCartRepository(api));

    final first = cubit.setQuantity(1, 20);
    final second = await cubit.setQuantity(1, 30);

    expect(second, isFalse);
    expect(await first, isTrue);
    expect(api.quantityCalls, 1);
    await cubit.close();
  });
}

class FakeCartApi implements PurchaseCartRemoteDataSource {
  Map<String, dynamic>? lastAdd;
  List<int>? lastSwitch;
  int quantityCalls = 0;
  String? lastCardNumber;
  bool supplierShort = false;

  @override
  Future<Response<dynamic>> getCart() async => _cart();

  @override
  Future<Response<dynamic>> addItem(int medicineId, int quantity) async {
    lastAdd = {'medicine_id': medicineId, 'quantity': quantity};

    return _cart();
  }

  @override
  Future<Response<dynamic>> setQuantity(int itemId, int quantity) async {
    quantityCalls++;

    return _cart();
  }

  @override
  Future<Response<dynamic>> removeItem(int itemId) async => _cart();

  @override
  Future<Response<dynamic>> clear() async => _cart();

  @override
  Future<Response<dynamic>> switchSupplier(int itemId, int medicineId) async {
    lastSwitch = [itemId, medicineId];

    return _cart();
  }

  @override
  Future<Response<dynamic>> checkout(
    String paymentMethod,
    String? cardNumber,
  ) async {
    lastCardNumber = cardNumber;

    if (supplierShort) {
      throw DioException(
        requestOptions: RequestOptions(path: '/purchase-cart/checkout'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/purchase-cart/checkout'),
          statusCode: 409,
          data: {
            'message':
                'Only 4 units of Salbutamol Inhaler are available from '
                'this supplier.',
            'code': 'supplier_stock_insufficient',
            'medicine': {
              'id': 44,
              'name': 'Salbutamol Inhaler',
              'available_quantity': 4,
              'requested_quantity': 50,
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }

    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/purchase-cart/checkout'),
      statusCode: 201,
      data: {
        'code': 'purchase_placed',
        'total': 126000,
        'orders': [
          {'id': 1, 'supplier_name': 'Barada Pharma Distribution'},
          {'id': 2, 'supplier_name': 'Al-Shahba Pharmaceutical Trading'},
        ],
      },
    );
  }

  Response<dynamic> _cart() => Response<dynamic>(
    requestOptions: RequestOptions(path: '/purchase-cart'),
    data: {
      'total': 126000,
      'item_count': 3,
      'suggested_count': 1,
      'unavailable_count': 1,
      'expired_count': 1,
      'expiring_soon_count': 1,
      'suppliers': [
        {
          'supplier': {
            'id': 1,
            'name': 'Barada Pharma Distribution',
            'phone': '0930111222',
            'address': 'Al-Mazzeh, Damascus',
          },
          'subtotal': 90000,
          'items': [
            {
              'id': 1,
              'quantity': 10,
              'subtotal': 80000,
              'added_by': 'pharmacist',
              'available': true,
              'expired': false,
              'expiring_soon': false,
              'medicine': {
                'id': 11,
                'name': 'Amoxicillin 500mg',
                'category': 'Antibiotics',
                'cost_price': 8000,
                'suggested_retail': 12500,
                'available_quantity': 200,
              },
              'cheaper_elsewhere': {
                'medicine_id': 44,
                'supplier_id': 3,
                'supplier_name': 'Al-Shahba Pharmaceutical Trading',
                'cost_price': 7000,
                'saving': 10000,
              },
            },
            {
              'id': 2,
              'quantity': 5,
              'subtotal': 10000,
              'added_by': 'pharmacist',
              'available': true,
              'expired': true,
              'expiring_soon': false,
              'medicine': {
                'id': 12,
                'name': 'Aspirin 100mg',
                'category': 'Painkillers',
                'cost_price': 2000,
                'suggested_retail': 4000,
                'available_quantity': 300,
                'expire_date': '2026-01-01',
              },
              'cheaper_elsewhere': null,
            },
          ],
        },
        {
          'supplier': {
            'id': 3,
            'name': 'Al-Shahba Pharmaceutical Trading',
            'phone': '0932333444',
            'address': 'Al-Furqan, Aleppo',
          },
          'subtotal': 36000,
          'items': [
            {
              'id': 3,
              'quantity': 2,
              'subtotal': 36000,
              'added_by': 'app',
              'available': false,
              'expired': false,
              'expiring_soon': true,
              'medicine': {
                'id': 33,
                'name': 'Salbutamol Inhaler',
                'category': 'Respiratory',
                'cost_price': 18000,
                'suggested_retail': 26000,
                'available_quantity': 1,
              },
              'cheaper_elsewhere': null,
            },
          ],
        },
      ],
    },
  );
}
