import 'package:dio/dio.dart';

abstract class InventoryRemoteDataSource {
  Future<Response<dynamic>> getMedicines();

  Future<Response<dynamic>> addMedicine(Map<String, dynamic> data);

  Future<Response<dynamic>> editMedicine(int id, Map<String, dynamic> data);

  Future<Response<dynamic>> writeOff(int medicineId, Map<String, dynamic> data);
}
