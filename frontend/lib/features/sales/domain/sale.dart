/// A completed sale (`sales` table) with its line items.
/// Backend payment methods are lowercase: cash | card | insurance.
class Sale {
  final int id;
  final String? customerName;
  final String paymentMethod;
  final double totalPrice;
  final DateTime? date;
  final List<SaleItem> items;

  const Sale({
    required this.id,
    required this.paymentMethod,
    required this.totalPrice,
    required this.items,
    this.customerName,
    this.date,
  });

  String get paymentLabel => switch (paymentMethod) {
    'cash' => 'Cash',
    'card' => 'Card',
    'insurance' => 'Insurance',
    _ => paymentMethod,
  };

  int get totalQuantity => items.fold(0, (sum, item) => sum + item.quantity);

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

  static int _toInt(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

  factory Sale.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final rawDate = json['date']?.toString();
    final customer = json['customer_name']?.toString();

    return Sale(
      id: _toInt(json['id']),
      customerName: customer == null || customer.isEmpty ? null : customer,
      paymentMethod: json['payment_method']?.toString() ?? '',
      totalPrice: _toDouble(json['total_price']),
      date: rawDate == null || rawDate.isEmpty
          ? null
          : DateTime.tryParse(rawDate),
      items: rawItems is List
          ? rawItems
                .whereType<Map<String, dynamic>>()
                .map(SaleItem.fromJson)
                .toList(growable: false)
          : const <SaleItem>[],
    );
  }
}

class SaleItem {
  final int id;
  final String medicineName;
  final int quantity;
  final double price;

  const SaleItem({
    required this.id,
    required this.medicineName,
    required this.quantity,
    required this.price,
  });

  double get lineTotal => price * quantity;

  factory SaleItem.fromJson(Map<String, dynamic> json) {
    final medicine = json['medicine'];
    return SaleItem(
      id: Sale._toInt(json['id']),
      medicineName: medicine is Map<String, dynamic>
          ? (medicine['name']?.toString() ?? '-')
          : '-',
      quantity: Sale._toInt(json['quantity']),
      price: Sale._toDouble(json['price']),
    );
  }
}

class SaleReturnable {
  const SaleReturnable({
    required this.saleId,
    required this.isOpen,
    required this.hoursLeft,
    required this.items,
  });

  final int saleId;
  final bool isOpen;
  final int hoursLeft;
  final List<ReturnableSaleItem> items;

  bool get hasItems => items.any((item) => item.returnable > 0);

  factory SaleReturnable.fromJson(Map<String, dynamic> json) {
    final sale = json['sale'];
    final rawItems = json['items'];
    return SaleReturnable(
      saleId: sale is Map ? Sale._toInt(sale['id']) : 0,
      isOpen: json['returnable'] == true,
      hoursLeft: Sale._toInt(json['hours_left']),
      items: rawItems is List
          ? rawItems
                .whereType<Map<String, dynamic>>()
                .map(ReturnableSaleItem.fromJson)
                .toList(growable: false)
          : const <ReturnableSaleItem>[],
    );
  }
}

class ReturnableSaleItem {
  const ReturnableSaleItem({
    required this.saleItemId,
    required this.name,
    required this.quantity,
    required this.returned,
    required this.returnable,
    required this.unitPrice,
  });

  final int saleItemId;
  final String name;
  final int quantity;
  final int returned;
  final int returnable;
  final double unitPrice;

  factory ReturnableSaleItem.fromJson(Map<String, dynamic> json) {
    return ReturnableSaleItem(
      saleItemId: Sale._toInt(json['sale_item_id']),
      name: json['name']?.toString() ?? 'Medicine',
      quantity: Sale._toInt(json['quantity']),
      returned: Sale._toInt(json['returned']),
      returnable: Sale._toInt(json['returnable']),
      unitPrice: Sale._toDouble(json['unit_price']),
    );
  }
}

class SaleReturnResult {
  const SaleReturnResult({required this.refundAmount, required this.restocked});

  final double refundAmount;
  final bool restocked;

  factory SaleReturnResult.fromJson(Map<String, dynamic> json) {
    return SaleReturnResult(
      refundAmount: Sale._toDouble(json['refund_amount']),
      restocked: json['restocked'] == true,
    );
  }
}
