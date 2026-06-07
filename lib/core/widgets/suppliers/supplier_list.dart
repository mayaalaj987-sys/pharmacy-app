import 'package:flutter/material.dart';

import '../../../../../core/data/supplier_data.dart';
import '../../../features/auth/data/models/supplier_model.dart';

import '../../../features/auth/presentation/pages/add_supplier_page.dart';
import 'supplier_card.dart';

class SupplierList extends StatefulWidget {
  const SupplierList({super.key});


  @override
  State<SupplierList> createState() => _SupplierListState();
}

class _SupplierListState extends State<SupplierList> {
  @override
  Widget build(BuildContext context) {
    if (suppliers.isEmpty) {
      return const Center(
        child: Text(
          "No suppliers added yet",
          style: TextStyle(
            fontSize: 16,
          ),
        ),
      );
    }

    return ListView.builder(
      itemCount: suppliers.length,

      itemBuilder: (context, index) {
        final SupplierModel supplier = suppliers[index];

        return SupplierCard(
          supplier: supplier,

          onEdit: () async {
            await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => AddSupplierPage(
                  supplier: supplier,
                  index: index,
                ),
              ),
            );

            setState(() {});
          },

          onDelete: () {
            showDialog(
              context: context,

              builder: (context) {
                return AlertDialog(
                  title: const Text(
                    "Delete Supplier",
                  ),

                  content: Text(
                    "Are you sure you want to delete ${supplier.name} ?",
                  ),

                  actions: [
                    TextButton(
                      onPressed: () {
                        Navigator.pop(context);
                      },

                      child: const Text("Cancel"),
                    ),

                    TextButton(
                      onPressed: () {
                        setState(() {
                          suppliers.removeAt(index);
                        });

                        Navigator.pop(context);
                      },

                      child: const Text("Delete"),
                    ),
                  ],
                );
              },
            );
          },
        );
      },
    );
  }
}