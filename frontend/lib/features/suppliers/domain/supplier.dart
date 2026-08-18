/// Supplier catalogue entity mirroring the backend `suppliers` table.
/// The backend exposes no `contact_person` column, so none is modelled here.
class Supplier {
  final int id;
  final String name;
  final String phone;
  final String email;
  final String address;

  const Supplier({
    required this.id,
    required this.name,
    required this.phone,
    required this.email,
    required this.address,
  });

  factory Supplier.fromJson(Map<String, dynamic> json) {
    return Supplier(
      id: json['id'] is num
          ? (json['id'] as num).toInt()
          : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
    );
  }
}

/// A catalogue medicine offered by a supplier (`GET /suppliers/{id}/medicines`).
///
/// Two prices, and they are not interchangeable. [price] is what the pharmacy
/// pays the supplier and is what an order is billed at; [suggestedRetail] is
/// what the supplier reckons it sells for, which the pharmacy is free to
/// ignore. Showing the retail figure on a buying screen tells the pharmacist a
/// number they will never be charged.
class SupplierMedicine {
  final int id;
  final String name;
  final String category;
  final double price;
  final double suggestedRetail;
  final int availableQuantity;

  const SupplierMedicine({
    required this.id,
    required this.name,
    required this.category,
    required this.price,
    required this.suggestedRetail,
    required this.availableQuantity,
  });

  factory SupplierMedicine.fromJson(Map<String, dynamic> json) {
    double toDouble(dynamic v) =>
        v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;
    int toInt(dynamic v) =>
        v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

    return SupplierMedicine(
      id: toInt(json['id']),
      name: json['name']?.toString() ?? '',
      category: json['category_medicine']?.toString() ?? '',
      price: toDouble(json['cost_price']),
      suggestedRetail: toDouble(json['selling_price']),
      availableQuantity: toInt(json['quantity']),
    );
  }
}
