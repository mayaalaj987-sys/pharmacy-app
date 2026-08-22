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

  Future<StockWriteOffHistory> fetchWriteOffs() async {
    try {
      final response = await api.getWriteOffs();
      final data = response.data;
      if (data is! Map<String, dynamic>) return StockWriteOffHistory.empty;
      final raw = data['write_offs'];
      return StockWriteOffHistory(
        totalValue: _toDouble(data['total_value']),
        records: raw is List
            ? raw
                  .whereType<Map<String, dynamic>>()
                  .map(StockWriteOffRecord.fromJson)
                  .toList(growable: false)
            : const <StockWriteOffRecord>[],
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  static double _toDouble(dynamic value) => value is num
      ? value.toDouble()
      : double.tryParse(value?.toString() ?? '') ?? 0;

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

class StockWriteOffHistory {
  const StockWriteOffHistory({required this.totalValue, required this.records});

  final double totalValue;
  final List<StockWriteOffRecord> records;

  static const empty = StockWriteOffHistory(totalValue: 0, records: []);
}

class StockWriteOffRecord {
  const StockWriteOffRecord({
    required this.id,
    required this.medicineName,
    required this.quantity,
    required this.value,
    required this.reason,
    this.note,
    this.recordedAt,
  });

  final int id;
  final String medicineName;
  final int quantity;
  final double value;
  final String reason;
  final String? note;
  final DateTime? recordedAt;

  factory StockWriteOffRecord.fromJson(Map<String, dynamic> json) {
    final note = json['note']?.toString().trim();
    return StockWriteOffRecord(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      medicineName: json['medicine_name']?.toString() ?? 'Medicine',
      quantity: int.tryParse(json['quantity']?.toString() ?? '') ?? 0,
      value: InventoryRepository._toDouble(json['value']),
      reason: json['reason']?.toString() ?? '',
      note: note == null || note.isEmpty ? null : note,
      recordedAt: DateTime.tryParse(json['recorded_at']?.toString() ?? ''),
    );
  }
}
