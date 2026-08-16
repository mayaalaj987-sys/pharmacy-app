import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/suppliers/data/suppliers_remote_data_source.dart';
import 'package:phamacy_managment/features/suppliers/data/suppliers_repository.dart';
import 'package:phamacy_managment/features/suppliers/presentation/cubit/suppliers_cubit.dart';
import 'package:phamacy_managment/features/suppliers/presentation/cubit/suppliers_state.dart';

void main() {
  test('fetchSuppliers parses the Laravel suppliers envelope', () async {
    final repo = SuppliersRepository(FakeSuppliersApi());

    final suppliers = await repo.fetchSuppliers();

    expect(suppliers, hasLength(1));
    expect(suppliers.first.id, 3);
    expect(suppliers.first.name, 'Medical Pharma');
    expect(suppliers.first.phone, '099999999');
    expect(suppliers.first.email, 'medical@example.test');
    expect(suppliers.first.address, 'Damascus');
  });

  test('fetchSupplierMedicines maps catalogue rows to the UI shape', () async {
    final repo = SuppliersRepository(FakeSuppliersApi());

    final medicines = await repo.fetchSupplierMedicines(3);

    expect(medicines, hasLength(1));
    expect(medicines.first.name, 'Augmentin');
    expect(medicines.first.category, 'Antibiotics');
    expect(medicines.first.price, 12.0);
    expect(medicines.first.availableQuantity, 200);
  });

  test('cubit load exposes ready state from the API', () async {
    final cubit = SuppliersCubit(SuppliersRepository(FakeSuppliersApi()));

    await cubit.load();

    expect(cubit.state.status, SuppliersStatus.ready);
    expect(cubit.state.suppliers, hasLength(1));
    await cubit.close();
  });

  test('loadMedicines caches per supplier and clears the loading flag', () async {
    final api = FakeSuppliersApi();
    final cubit = SuppliersCubit(SuppliersRepository(api));

    await cubit.loadMedicines(3);

    expect(cubit.state.loadingMedicinesFor, isNull);
    expect(cubit.medicinesFor(3), hasLength(1));
    expect(api.medicineCalls, 1);
    await cubit.close();
  });

  test('a supplier list failure surfaces an error state', () async {
    final cubit = SuppliersCubit(
      SuppliersRepository(FakeSuppliersApi()..fail = true),
    );

    await cubit.load();

    expect(cubit.state.status, SuppliersStatus.failure);
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });
}

class FakeSuppliersApi implements SuppliersRemoteDataSource {
  bool fail = false;
  int medicineCalls = 0;

  @override
  Future<Response<dynamic>> getSuppliers() async {
    if (fail) {
      throw DioException(
        requestOptions: RequestOptions(path: '/suppliers'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/suppliers'),
          statusCode: 500,
          data: {'message': 'Server error'},
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/suppliers'),
      data: {
        'suppliers': [
          {
            'id': 3,
            'name': 'Medical Pharma',
            'phone': '099999999',
            'email': 'medical@example.test',
            'address': 'Damascus',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> getSupplierMedicines(int supplierId) async {
    medicineCalls++;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/suppliers/$supplierId/medicines'),
      data: {
        'supplier': 'Medical Pharma',
        'medicines': [
          {
            'id': 11,
            'name': 'Augmentin',
            'category_medicine': 'Antibiotics',
            'selling_price': '12.00',
            'quantity': 200,
          },
        ],
      },
    );
  }
}
