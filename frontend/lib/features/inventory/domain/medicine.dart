/// Canonical medicine entity mirroring the backend `medicines` table.
/// Field names follow the Laravel contract (category_medicine, expire_date, qr_code).
class Medicine {
  final int id;
  final String name;
  final String category;
  final String manufacturer;
  final double sellingPrice;
  final double costPrice;
  final int quantity;
  final int? reorderLevel;
  final DateTime? expireDate;
  final String? qrCode;
  final int? supplierId;

  const Medicine({
    required this.id,
    required this.name,
    required this.category,
    required this.manufacturer,
    required this.sellingPrice,
    required this.costPrice,
    required this.quantity,
    this.reorderLevel,
    this.expireDate,
    this.qrCode,
    this.supplierId,
  });

  bool get isLowStock =>
      reorderLevel != null && reorderLevel! > 0 && quantity <= reorderLevel!;

  bool get isExpired =>
      expireDate != null && expireDate!.isBefore(DateTime.now());

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int _toInt(dynamic value) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  factory Medicine.fromJson(Map<String, dynamic> json) {
    final rawExpire = json['expire_date']?.toString();
    return Medicine(
      id: _toInt(json['id']),
      name: json['name']?.toString() ?? '',
      category: json['category_medicine']?.toString() ?? '',
      manufacturer: json['manufacturer']?.toString() ?? '',
      sellingPrice: _toDouble(json['selling_price']),
      costPrice: _toDouble(json['cost_price']),
      quantity: _toInt(json['quantity']),
      reorderLevel: json['reorder_level'] == null
          ? null
          : _toInt(json['reorder_level']),
      expireDate: rawExpire == null || rawExpire.isEmpty
          ? null
          : DateTime.tryParse(rawExpire),
      qrCode: json['qr_code']?.toString(),
      supplierId: json['supplier_id'] == null
          ? null
          : _toInt(json['supplier_id']),
    );
  }
}
