/// How a stock row should be treated on the shelf.
///
/// Ordered by urgency: [expired] outranks everything because that stock must
/// never be sold, and [outOfStock] outranks the softer warnings because there
/// is nothing left to warn about.
enum MedicineStatus {
  expired,
  outOfStock,
  expiringSoon,
  reorder,
  healthy;

  String get label => switch (this) {
    MedicineStatus.expired => 'Expired',
    MedicineStatus.outOfStock => 'Out of stock',
    MedicineStatus.expiringSoon => 'Expiring soon',
    MedicineStatus.reorder => 'Reorder',
    MedicineStatus.healthy => 'Valid',
  };
}

/// Canonical medicine entity mirroring the backend `medicines` table.
/// Field names follow the Laravel contract (category_medicine, expire_date).
///
/// Barcodes were removed from the product. The backend column survives as a
/// nullable leftover the seeders key on, but nothing in the app reads it.
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
    this.supplierId,
  });

  /// Window the backend also uses for its "expiring" report.
  static const expiringSoonDays = 90;

  bool get isLowStock =>
      reorderLevel != null && reorderLevel! > 0 && quantity <= reorderLevel!;

  bool get isOutOfStock => quantity <= 0;

  /// Compared against the start of today so a medicine stays sellable through
  /// its final day — matching the server's cut-off exactly.
  bool get isExpired {
    final expiry = expireDate;
    if (expiry == null) return false;
    final today = DateTime.now();

    return expiry.isBefore(DateTime(today.year, today.month, today.day));
  }

  /// Still valid, but close enough that it should be flagged before selling.
  bool get isExpiringSoon {
    final days = daysUntilExpiry;

    return !isExpired && days != null && days <= expiringSoonDays;
  }

  /// Whole days from today until expiry. Negative once expired.
  int? get daysUntilExpiry {
    final expiry = expireDate;
    if (expiry == null) return null;
    final today = DateTime.now();

    return DateTime(
      expiry.year,
      expiry.month,
      expiry.day,
    ).difference(DateTime(today.year, today.month, today.day)).inDays;
  }

  MedicineStatus get status {
    if (isExpired) return MedicineStatus.expired;
    if (isOutOfStock) return MedicineStatus.outOfStock;
    if (isExpiringSoon) return MedicineStatus.expiringSoon;
    if (isLowStock) return MedicineStatus.reorder;

    return MedicineStatus.healthy;
  }

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
      supplierId: json['supplier_id'] == null
          ? null
          : _toInt(json['supplier_id']),
    );
  }
}
