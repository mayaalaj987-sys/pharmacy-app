import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/supplier.dart';
import 'suppliers_remote_data_source.dart';

class SuppliersRepository {
  final SuppliersRemoteDataSource api;

  const SuppliersRepository(this.api);

  Future<List<Supplier>> fetchSuppliers() async {
    try {
      final response = await api.getSuppliers();
      final data = response.data;
      final raw = data is Map<String, dynamic> ? data['suppliers'] : null;
      if (raw is! List) return const <Supplier>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(Supplier.fromJson)
          .toList(growable: false);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<List<SupplierMedicine>> fetchSupplierMedicines(int supplierId) async {
    try {
      final response = await api.getSupplierMedicines(supplierId);
      final data = response.data;
      final raw = data is Map<String, dynamic> ? data['medicines'] : null;
      if (raw is! List) return const <SupplierMedicine>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(SupplierMedicine.fromJson)
          .toList(growable: false);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
