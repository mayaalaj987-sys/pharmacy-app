import 'medicine_model.dart';
class PosCartItem {
  final MedicineModel medicine;

  int quantity;

  PosCartItem({
    required this.medicine,
    required this.quantity,
  });

  double get total =>
      (double.tryParse(medicine.sellingPrice) ?? 0) * quantity;
}