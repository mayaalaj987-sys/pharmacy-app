import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'suppliers_remote_data_source.dart';

class SuppliersApi implements SuppliersRemoteDataSource {
  final Dio dio;

  SuppliersApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getSuppliers() {
    return dio.get(ApiConstants.suppliers);
  }

  @override
  Future<Response<dynamic>> getSupplierMedicines(int supplierId) {
    return dio.get('${ApiConstants.suppliers}/$supplierId/medicines');
  }
}
