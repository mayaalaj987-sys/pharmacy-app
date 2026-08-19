/// What the pharmacy intends to buy, before it has bought it.
///
/// Grouped by supplier because that is how it will be bought: one cart becomes
/// one order per supplier at checkout, since each of them ships and invoices on
/// its own. The pharmacist should see that split before paying, not after.
library;

double _toDouble(dynamic value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;

int _toInt(dynamic value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

class PurchaseCart {
  final List<CartSupplierGroup> groups;
  final double total;
  final int itemCount;

  /// Lines the app queued after a sale ran stock down, still awaiting a verdict.
  final int suggestedCount;

  /// Lines the supplier can no longer fill in full.
  final int unavailableCount;

  /// Lines that have already expired. Checkout refuses these outright.
  final int expiredCount;

  /// Lines close enough to expiry to be worth a second thought, but allowed:
  /// short dating is sometimes exactly what a pharmacy wants.
  final int expiringSoonCount;

  const PurchaseCart({
    this.groups = const <CartSupplierGroup>[],
    this.total = 0,
    this.itemCount = 0,
    this.suggestedCount = 0,
    this.unavailableCount = 0,
    this.expiredCount = 0,
    this.expiringSoonCount = 0,
  });

  const PurchaseCart.empty() : this();

  bool get isEmpty => itemCount == 0;

  factory PurchaseCart.fromJson(Map<String, dynamic> json) {
    final raw = json['suppliers'];

    return PurchaseCart(
      groups: raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(CartSupplierGroup.fromJson)
                .toList(growable: false)
          : const <CartSupplierGroup>[],
      total: _toDouble(json['total']),
      itemCount: _toInt(json['item_count']),
      suggestedCount: _toInt(json['suggested_count']),
      unavailableCount: _toInt(json['unavailable_count']),
      expiredCount: _toInt(json['expired_count']),
      expiringSoonCount: _toInt(json['expiring_soon_count']),
    );
  }
}

/// Everything being bought from one supplier, and what it comes to.
class CartSupplierGroup {
  final int supplierId;
  final String supplierName;
  final String supplierAddress;
  final List<CartLine> items;
  final double subtotal;

  const CartSupplierGroup({
    required this.supplierId,
    required this.supplierName,
    required this.supplierAddress,
    required this.items,
    required this.subtotal,
  });

  factory CartSupplierGroup.fromJson(Map<String, dynamic> json) {
    final supplier = json['supplier'];
    final raw = json['items'];

    return CartSupplierGroup(
      supplierId: supplier is Map<String, dynamic> ? _toInt(supplier['id']) : 0,
      supplierName: supplier is Map<String, dynamic>
          ? supplier['name']?.toString() ?? ''
          : '',
      supplierAddress: supplier is Map<String, dynamic>
          ? supplier['address']?.toString() ?? ''
          : '',
      items: raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(CartLine.fromJson)
                .toList(growable: false)
          : const <CartLine>[],
      subtotal: _toDouble(json['subtotal']),
    );
  }
}

class CartLine {
  final int id;
  final int medicineId;
  final String name;
  final String category;
  final int quantity;
  final double unitCost;
  final double subtotal;

  /// What the supplier still holds. Shown when it cannot cover the line.
  final int availableQuantity;
  final bool available;

  /// True when the app queued this line and the pharmacist has not touched it.
  final bool suggested;

  /// Out of date already: the POS would refuse to sell it, so checkout refuses
  /// to buy it.
  final bool expired;

  /// Close to expiry, allowed, but worth seeing before paying.
  final bool expiringSoon;
  final String? expiresOn;

  final CheaperOffer? cheaperElsewhere;

  const CartLine({
    required this.id,
    required this.medicineId,
    required this.name,
    required this.category,
    required this.quantity,
    required this.unitCost,
    required this.subtotal,
    required this.availableQuantity,
    required this.available,
    required this.suggested,
    required this.expired,
    required this.expiringSoon,
    this.expiresOn,
    this.cheaperElsewhere,
  });

  factory CartLine.fromJson(Map<String, dynamic> json) {
    final medicine = json['medicine'] is Map<String, dynamic>
        ? json['medicine'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final cheaper = json['cheaper_elsewhere'];

    return CartLine(
      id: _toInt(json['id']),
      medicineId: _toInt(medicine['id']),
      name: medicine['name']?.toString() ?? '',
      category: medicine['category']?.toString() ?? '',
      quantity: _toInt(json['quantity']),
      unitCost: _toDouble(medicine['cost_price']),
      subtotal: _toDouble(json['subtotal']),
      availableQuantity: _toInt(medicine['available_quantity']),
      available: json['available'] == true,
      suggested: json['added_by']?.toString() == 'app',
      expired: json['expired'] == true,
      expiringSoon: json['expiring_soon'] == true,
      expiresOn: medicine['expire_date']?.toString(),
      cheaperElsewhere: cheaper is Map<String, dynamic>
          ? CheaperOffer.fromJson(cheaper)
          : null,
    );
  }
}

/// The same drug going cheaper at another supplier, and by how much.
///
/// Derived by the server on every read rather than stored, because the
/// catalogue is shared and its prices move.
class CheaperOffer {
  final int medicineId;
  final String supplierName;
  final double unitCost;
  final double saving;

  const CheaperOffer({
    required this.medicineId,
    required this.supplierName,
    required this.unitCost,
    required this.saving,
  });

  factory CheaperOffer.fromJson(Map<String, dynamic> json) {
    return CheaperOffer(
      medicineId: _toInt(json['medicine_id']),
      supplierName: json['supplier_name']?.toString() ?? '',
      unitCost: _toDouble(json['cost_price']),
      saving: _toDouble(json['saving']),
    );
  }
}
