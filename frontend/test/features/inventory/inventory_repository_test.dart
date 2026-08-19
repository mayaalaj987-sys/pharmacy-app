import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/inventory/data/inventory_remote_data_source.dart';
import 'package:phamacy_managment/features/inventory/data/inventory_repository.dart';
import 'package:phamacy_managment/features/inventory/presentation/cubit/inventory_cubit.dart';
import 'package:phamacy_managment/features/inventory/presentation/cubit/inventory_state.dart';

void main() {
  test('a write-off posts the quantity, the reason and nothing else', () async {
    // The note is optional and an empty one is not a note. Sending it would
    // store a blank string that reads as "somebody explained" in the history.
    final api = FakeInventoryApi();

    await InventoryRepository(
      api,
    ).writeOff(7, quantity: 41, reason: 'expired', note: '');

    expect(api.lastWriteOffPayload, {'quantity': 41, 'reason': 'expired'});
  });

  test('a note is sent when there is one', () async {
    final api = FakeInventoryApi();

    await InventoryRepository(api).writeOff(
      7,
      quantity: 2,
      reason: 'damaged',
      note: 'fridge failed overnight',
    );

    expect(api.lastWriteOffPayload!['note'], 'fridge failed overnight');
  });

  test('fetchMedicines parses the Laravel medicines envelope', () async {
    final repo = InventoryRepository(FakeInventoryApi());

    final medicines = await repo.fetchMedicines();

    expect(medicines, hasLength(1));
    final m = medicines.first;
    expect(m.id, 5);
    expect(m.name, 'Amoxicillin');
    expect(m.category, 'Antibiotics');
    expect(m.sellingPrice, 12.5);
    expect(m.costPrice, 8.0);
    expect(m.quantity, 3);
    expect(m.reorderLevel, 10);
    expect(m.expireDate?.year, 2027);
    expect(m.isLowStock, isTrue);
  });

  test('addMedicine posts the backend field names, not UI names', () async {
    final api = FakeInventoryApi();
    final repo = InventoryRepository(api);

    await repo.addMedicine({
      'name': 'New Med',
      'category_medicine': 'Vitamins',
      'manufacturer': 'Acme',
      'selling_price': 5.0,
      'cost_price': 2.0,
      'quantity': 7,
      'expire_date': '2027-01-01',
      'qr_code': 12345,
    });

    expect(api.lastAddPayload!['category_medicine'], 'Vitamins');
    expect(api.lastAddPayload!['expire_date'], '2027-01-01');
    expect(api.lastAddPayload!['qr_code'], 12345);
    // UI-only names must never reach the API.
    expect(api.lastAddPayload!.containsKey('category'), isFalse);
    expect(api.lastAddPayload!.containsKey('barcode'), isFalse);
    expect(api.lastAddPayload!.containsKey('notes'), isFalse);
  });

  test('cubit load exposes ready state backed by the API', () async {
    final cubit = InventoryCubit(InventoryRepository(FakeInventoryApi()));

    await cubit.load();

    expect(cubit.state.status, InventoryStatus.ready);
    expect(cubit.state.medicines, hasLength(1));
    await cubit.close();
  });

  test(
    'a failed add surfaces an error and does not corrupt the list',
    () async {
      final api = FakeInventoryApi()..failAdd = true;
      final cubit = InventoryCubit(InventoryRepository(api));
      await cubit.load();

      final ok = await cubit.addMedicine({'name': 'bad'});

      expect(ok, isFalse);
      expect(cubit.state.error, isNotNull);
      expect(cubit.state.saving, isFalse);
      expect(cubit.state.medicines, hasLength(1));
      await cubit.close();
    },
  );

  test('a successful add refetches authoritative server state', () async {
    final api = FakeInventoryApi();
    final cubit = InventoryCubit(InventoryRepository(api));

    final ok = await cubit.addMedicine({'name': 'ok'});

    expect(ok, isTrue);
    // one refetch after the write
    expect(api.listCalls, 1);
    expect(cubit.state.status, InventoryStatus.ready);
    await cubit.close();
  });
}

class FakeInventoryApi implements InventoryRemoteDataSource {
  Map<String, dynamic>? lastAddPayload;
  Map<String, dynamic>? lastWriteOffPayload;
  bool failAdd = false;
  int listCalls = 0;

  @override
  Future<Response<dynamic>> writeOff(
    int medicineId,
    Map<String, dynamic> data,
  ) async {
    lastWriteOffPayload = data;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/medicines/$medicineId/write-off'),
      statusCode: 201,
      data: {'code': 'stock_written_off'},
    );
  }

  @override
  Future<Response<dynamic>> getMedicines() async {
    listCalls++;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/medicines'),
      data: {
        'medicines_count': 1,
        'medicines': [
          {
            'id': 5,
            'name': 'Amoxicillin',
            'category_medicine': 'Antibiotics',
            'manufacturer': 'Acme',
            'selling_price': '12.50',
            'cost_price': '8.00',
            'quantity': 3,
            'reorder_level': 10,
            'expire_date': '2027-03-01T00:00:00.000000Z',
            'qr_code': '999',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> addMedicine(Map<String, dynamic> data) async {
    lastAddPayload = data;
    if (failAdd) {
      throw DioException(
        requestOptions: RequestOptions(path: '/medicines/add'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/medicines/add'),
          statusCode: 422,
          data: {
            'message': 'The given data was invalid.',
            'errors': {
              'qr_code': ['The qr code field is required.'],
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/medicines/add'),
      statusCode: 201,
      data: {
        'message': 'Medicine added Successfully',
        'medicine': {
          'id': 9,
          'name': data['name'],
          'category_medicine': 'Vitamins',
          'manufacturer': 'Acme',
          'selling_price': 5,
          'cost_price': 2,
          'quantity': 7,
        },
      },
    );
  }

  @override
  Future<Response<dynamic>> editMedicine(
    int id,
    Map<String, dynamic> data,
  ) async {
    return addMedicine(data);
  }
}
