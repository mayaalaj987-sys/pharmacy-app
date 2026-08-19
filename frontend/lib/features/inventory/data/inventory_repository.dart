import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/medicine.dart';
import 'inventory_remote_data_source.dart';

class InventoryRepository {
  final InventoryRemoteDataSource api;

  const InventoryRepository(this.api);

  Future<List<Medicine>> fetchMedicines() async {
    try {
      final response = await api.getMedicines();
      return _parseList(response);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<Medicine> addMedicine(Map<String, dynamic> payload) async {
    try {
      final response = await api.addMedicine(payload);
      return _parseSingle(response);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<Medicine> editMedicine(int id, Map<String, dynamic> payload) async {
    try {
      final response = await api.editMedicine(id, payload);
      return _parseSingle(response);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Takes stock off a batch and books the loss against the pharmacy.
  ///
  /// Not the same as editing the quantity down, which is what this replaces:
  /// that recorded neither what happened nor what it cost, and the money left
  /// the books without appearing in any report.
  Future<void> writeOff(
    int medicineId, {
    required int quantity,
    required String reason,
    String? note,
  }) async {
    try {
      await api.writeOff(medicineId, {
        'quantity': quantity,
        'reason': reason,
        if (note != null && note.isNotEmpty) 'note': note,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  List<Medicine> _parseList(Response<dynamic> response) {
    final data = response.data;
    final raw = data is Map<String, dynamic> ? data['medicines'] : null;
    if (raw is! List) return const <Medicine>[];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(Medicine.fromJson)
        .toList(growable: false);
  }

  Medicine _parseSingle(Response<dynamic> response) {
    final data = response.data;
    final raw = data is Map<String, dynamic> ? data['medicine'] : null;
    if (raw is! Map<String, dynamic>) {
      throw const FormatException('Malformed medicine response.');
    }
    return Medicine.fromJson(raw);
  }
}
