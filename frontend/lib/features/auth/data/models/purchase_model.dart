class PurchaseModel {
  final String id;

  final String supplierName;

  final String medicineName;

  final int quantity;

  final double price;

  String status;

  final DateTime date;

  PurchaseModel({
    required this.id,
    required this.supplierName,
    required this.medicineName,
    required this.quantity,
    required this.price,
    required this.status,
    required this.date,
  });
}