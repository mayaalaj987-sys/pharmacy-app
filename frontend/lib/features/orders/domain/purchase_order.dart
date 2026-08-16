/// A purchase order (`orders` table) with its line items.
/// Backend statuses are lowercase: pending | received | cancelled.
class PurchaseOrder {
  final int id;
  final String supplierName;
  final String status;
  final double totalPrice;
  final String paymentMethod;
  final DateTime? date;
  final List<PurchaseOrderItem> items;

  const PurchaseOrder({
    required this.id,
    required this.supplierName,
    required this.status,
    required this.totalPrice,
    required this.paymentMethod,
    required this.items,
    this.date,
  });

  bool get isPending => status == 'pending';

  /// Title-cased label the existing status badge/UI expects.
  String get statusLabel => switch (status) {
        'received' => 'Received',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
        _ => status,
      };

  int get totalQuantity =>
      items.fold(0, (sum, item) => sum + item.quantity);

  /// The existing card shows one medicine line; summarise multi-item orders.
  String get medicinesSummary {
    if (items.isEmpty) return '-';
    if (items.length == 1) return items.first.medicineName;
    return '${items.first.medicineName} +${items.length - 1} more';
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

  static int _toInt(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

  factory PurchaseOrder.fromJson(Map<String, dynamic> json) {
    final supplier = json['supplier'];
    final rawItems = json['items'];
    final rawDate = json['date']?.toString();

    return PurchaseOrder(
      id: _toInt(json['id']),
      supplierName: supplier is Map<String, dynamic>
          ? (supplier['name']?.toString() ?? '-')
          : '-',
      status: json['status']?.toString() ?? 'pending',
      totalPrice: _toDouble(json['total_price']),
      paymentMethod: json['payment_method']?.toString() ?? '',
      date: rawDate == null || rawDate.isEmpty
          ? null
          : DateTime.tryParse(rawDate),
      items: rawItems is List
          ? rawItems
              .whereType<Map<String, dynamic>>()
              .map(PurchaseOrderItem.fromJson)
              .toList(growable: false)
          : const <PurchaseOrderItem>[],
    );
  }
}

class PurchaseOrderItem {
  final int id;
  final String medicineName;
  final int quantity;
  final double price;

  const PurchaseOrderItem({
    required this.id,
    required this.medicineName,
    required this.quantity,
    required this.price,
  });

  factory PurchaseOrderItem.fromJson(Map<String, dynamic> json) {
    final medicine = json['medicine'];
    return PurchaseOrderItem(
      id: PurchaseOrder._toInt(json['id']),
      medicineName: medicine is Map<String, dynamic>
          ? (medicine['name']?.toString() ?? '-')
          : '-',
      quantity: PurchaseOrder._toInt(json['quantity']),
      price: PurchaseOrder._toDouble(json['price']),
    );
  }
}
