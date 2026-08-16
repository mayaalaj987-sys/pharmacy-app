import 'package:dio/dio.dart';

abstract class SuppliersRemoteDataSource {
  Future<Response<dynamic>> getSuppliers();

  Future<Response<dynamic>> getSupplierMedicines(int supplierId);
}
