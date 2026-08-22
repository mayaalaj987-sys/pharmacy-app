import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'inventory_remote_data_source.dart';

class InventoryApi implements InventoryRemoteDataSource {
  final Dio dio;

  InventoryApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getMedicines() {
    return dio.get(ApiConstants.medicines);
  }

  @override
  Future<Response<dynamic>> addMedicine(Map<String, dynamic> data) {
    return dio.post(ApiConstants.medicineAdd, data: data);
  }

  @override
  Future<Response<dynamic>> editMedicine(int id, Map<String, dynamic> data) {
    return dio.post('${ApiConstants.medicineEdit}/$id', data: data);
  }

  @override
  Future<Response<dynamic>> writeOff(
    int medicineId,
    Map<String, dynamic> data,
  ) {
    return dio.post(
      '${ApiConstants.medicines}/$medicineId/write-off',
      data: data,
    );
  }

  @override
  Future<Response<dynamic>> getWriteOffs() {
    return dio.get('${ApiConstants.medicines}/write-offs');
  }
}
