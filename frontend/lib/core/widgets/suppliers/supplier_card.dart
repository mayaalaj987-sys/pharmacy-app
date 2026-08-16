import 'package:flutter/material.dart';

import '../../../features/auth/presentation/pages/supplier_details_page.dart';
import '../../../features/suppliers/domain/supplier.dart';
import '../../theme/app_colors.dart';

class SupplierCard extends StatelessWidget {
  final Supplier supplier;

  const SupplierCard({super.key, required this.supplier});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),

      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => SupplierDetailsPage(supplier: supplier),
          ),
        );
      },

      child: Card(
        color: AppColors.white,

        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),

        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

        child: Padding(
          padding: const EdgeInsets.all(16),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(
                supplier.name,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 10),

              Text("Phone: ${supplier.phone}"),

              Text("Email: ${supplier.email}"),

              Text("Address: ${supplier.address}"),

              const SizedBox(height: 12),
              const SizedBox(height: 8),

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),

                decoration: BoxDecoration(
                  color: Colors.green.shade50,

                  borderRadius:
                  BorderRadius.circular(20),
                ),

                child: Text(
                  "View medicines",
                  style: TextStyle(
                    color: Colors.green.shade700,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
