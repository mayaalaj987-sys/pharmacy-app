import 'package:phamacy_managment/features/auth/data/models/supplier_model.dart';

import '../../features/auth/data/models/supplier_medicine_model.dart';

List<SupplierModel> suppliers = [
  SupplierModel(
    name: "Medical Pharma",
    contactPerson: "Ahmad",
    phone: "099999999",
    email: "medical@gmail.com",
    address: "Damascus",

    medicines: [

      SupplierMedicineModel(
        name: "Augmentin",
        category: "Antibiotics",
        price: 12,
        availableQuantity: 200,
      ),

      SupplierMedicineModel(
        name: "Panadol",
        category: "Painkillers",
        price: 5,
        availableQuantity: 500,
      ),
    ],
  ),
];
