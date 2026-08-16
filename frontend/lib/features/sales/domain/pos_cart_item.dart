import '../../inventory/domain/medicine.dart';

/// A cart line backed by an authoritative server medicine row.
class PosCartItem {
  final Medicine medicine;
  int quantity;

  PosCartItem({required this.medicine, required this.quantity});

  double get total => medicine.sellingPrice * quantity;
}
