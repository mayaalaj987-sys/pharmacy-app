import 'package:flutter/material.dart';

import '../../../../core/data/purchase_data.dart';
import '../../data/models/purchase_model.dart';
import '../../data/models/supplier_model.dart';

class SupplierDetailsPage extends StatelessWidget {
  final SupplierModel supplier;

  const SupplierDetailsPage({
    super.key,
    required this.supplier,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(supplier.name),
      ),

      body: supplier.medicines.isEmpty

          ? const Center(
        child: Text(
          "No medicines available",
        ),
      )

          : ListView.builder(
        padding: const EdgeInsets.all(16),

        itemCount: supplier.medicines.length,

        itemBuilder: (context, index) {
          final medicine = supplier.medicines[index];

          return Card(
            margin: const EdgeInsets.only(
              bottom: 12,
            ),

            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),

            child: Padding(
              padding: const EdgeInsets.all(16),

              child: Column(
                crossAxisAlignment:
                CrossAxisAlignment.start,

                children: [

                  Row(
                    mainAxisAlignment:
                    MainAxisAlignment.spaceBetween,

                    children: [

                      Expanded(
                        child: Text(
                          medicine.name,

                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight:
                            FontWeight.bold,
                          ),
                        ),
                      ),

                      Text(
                        "\$${medicine.price}",

                        style: const TextStyle(
                          color: Colors.green,
                          fontWeight:
                          FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),

                    ],
                  ),

                  const SizedBox(height: 8),

                  Text(
                    medicine.category,

                    style: TextStyle(
                      color: Colors.grey.shade600,
                    ),
                  ),

                  const SizedBox(height: 4),

                  Text(
                    "Available: ${medicine.availableQuantity}",
                  ),

                  const SizedBox(height: 16),

                  SizedBox(
                    width: double.infinity,

                    child: ElevatedButton.icon(
                      onPressed: () {

                        purchases.add(
                          PurchaseModel(
                            id: DateTime.now()
                                .millisecondsSinceEpoch
                                .toString(),

                            supplierName:
                            supplier.name,

                            medicineName:
                            medicine.name,

                            quantity: 50,

                            price: medicine.price,

                            status: "Pending",

                            date: DateTime.now(),
                          ),
                        );

                        ScaffoldMessenger.of(context)
                            .showSnackBar(
                          SnackBar(
                            content: Text(
                              "${medicine.name} added to purchases",
                            ),
                          ),
                        );
                      },

                      icon: const Icon(
                        Icons.shopping_cart,
                      ),

                      label: const Text(
                        "Buy",
                      ),
                    ),
                  ),

                ],
              ),
            ),
          );
        },
      ),
    );
  }
}