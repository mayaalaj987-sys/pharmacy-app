/// What is about to arrive from a supplier, and what it should be priced at.
///
/// Read before receiving, because a delivery is the one moment the pharmacy
/// gets to set a margin: the supplier's cost is theirs to set, the shelf price
/// is the pharmacy's, and until this screen existed nobody was asked.
library;

double _toDouble(dynamic value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;

int _toInt(dynamic value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

class ReceivingPlan {
  final String supplierName;
  final double totalPrice;
  final List<ReceivingLine> items;

  const ReceivingPlan({
    required this.supplierName,
    required this.totalPrice,
    required this.items,
  });

  /// Drugs this pharmacy has never stocked, which have no price yet.
  int get newCount => items.where((item) => item.isNew).length;

  factory ReceivingPlan.fromJson(Map<String, dynamic> json) {
    final order = json['order'] is Map<String, dynamic>
        ? json['order'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final raw = json['items'];

    return ReceivingPlan(
      supplierName: order['supplier_name']?.toString() ?? '',
      totalPrice: _toDouble(order['total_price']),
      items: raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(ReceivingLine.fromJson)
                .toList(growable: false)
          : const <ReceivingLine>[],
    );
  }
}

class ReceivingLine {
  final int medicineId;
  final String name;
  final int quantity;

  /// What was paid per unit on this order, not the catalogue's price today.
  final double unitCost;

  /// True when the pharmacy does not stock this drug yet.
  final bool isNew;

  /// What it currently sells for here, or null when it is new.
  final double? currentSellingPrice;

  /// Their own price where there is one, otherwise the supplier's suggestion.
  final double suggestedSellingPrice;

  const ReceivingLine({
    required this.medicineId,
    required this.name,
    required this.quantity,
    required this.unitCost,
    required this.isNew,
    required this.currentSellingPrice,
    required this.suggestedSellingPrice,
  });

  factory ReceivingLine.fromJson(Map<String, dynamic> json) {
    return ReceivingLine(
      medicineId: _toInt(json['medicine_id']),
      name: json['name']?.toString() ?? '',
      quantity: _toInt(json['quantity']),
      unitCost: _toDouble(json['unit_cost']),
      isNew: json['is_new'] == true,
      currentSellingPrice: json['current_selling_price'] == null
          ? null
          : _toDouble(json['current_selling_price']),
      suggestedSellingPrice: _toDouble(json['suggested_selling_price']),
    );
  }
}
