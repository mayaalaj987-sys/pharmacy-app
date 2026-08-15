import 'supplier_medicine_model.dart';
class SupplierModel {
  final String name;
  final String contactPerson;
  final String phone;
  final String email;
  final String address;
  final List<SupplierMedicineModel> medicines;
  SupplierModel({
    required this.name,
    required this.contactPerson,
    required this.phone,
    required this.email,
    required this.address,
    required this.medicines,
  });
}