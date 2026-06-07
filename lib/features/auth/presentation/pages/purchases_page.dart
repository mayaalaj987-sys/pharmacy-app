import 'package:flutter/material.dart';

import '../../../../core/data/purchase_data.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';

class PurchasesPage extends StatefulWidget {
  const PurchasesPage({super.key});

  @override
  State<PurchasesPage> createState() => _PurchasesPageState();
}

class _PurchasesPageState extends State<PurchasesPage> {
  @override
  Widget build(BuildContext context) {
    final pendingCount = purchases.where((e) => e.status == "Pending").length;

    final receivedCount = purchases.where((e) => e.status == "Received").length;

    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Purchases"),
      ),

      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),

            child: Row(
              children: [
                Expanded(
                  child: _buildStatCard(
                    "Orders",
                    purchases.length.toString(),
                    Colors.blue,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: _buildStatCard(
                    "Pending",
                    pendingCount.toString(),
                    Colors.orange,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: _buildStatCard(
                    "Received",
                    receivedCount.toString(),
                    Colors.green,
                  ),
                ),
              ],
            ),
          ),

          Expanded(
            child: purchases.isEmpty
                ? const Center(child: Text("No purchase orders"))
                : ListView.builder(
                    itemCount: purchases.length,

                    itemBuilder: (context, index) {
                      final purchase = purchases[index];

                      return Card(
                        margin: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 8,
                        ),

                        child: Padding(
                          padding: const EdgeInsets.all(16),

                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,

                                children: [
                                  Text(
                                    purchase.medicineName,

                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),

                                  _buildStatusBadge(purchase.status),
                                ],
                              ),

                              const SizedBox(height: 8),

                              Text("Supplier: ${purchase.supplierName}"),

                              Text("Quantity: ${purchase.quantity}"),

                              Text("Price: \$${purchase.price}"),

                              const SizedBox(height: 12),

                              if (purchase.status == "Pending")
                                Row(
                                  children: [
                                    Expanded(
                                      child: ElevatedButton.icon(
                                        onPressed: () {
                                          setState(() {
                                            purchase.status = "Received";
                                          });
                                        },

                                        icon: const Icon(Icons.check),

                                        label: const Text("Receive"),
                                      ),
                                    ),

                                    const SizedBox(width: 10),

                                    Expanded(
                                      child: ElevatedButton.icon(
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: Colors.red,
                                        ),

                                        onPressed: () {
                                          setState(() {
                                            purchase.status = "Cancelled";
                                          });
                                        },

                                        icon: const Icon(Icons.close),

                                        label: const Text("Cancel"),
                                      ),
                                    ),
                                  ],
                                ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatCard(String title, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(14),

      decoration: BoxDecoration(
        color: color.withOpacity(.15),

        borderRadius: BorderRadius.circular(16),
      ),

      child: Column(
        children: [
          Text(
            value,

            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),

          const SizedBox(height: 6),

          Text(title),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color color;

    switch (status) {
      case "Received":
        color = Colors.green;
        break;

      case "Cancelled":
        color = Colors.red;
        break;

      default:
        color = Colors.orange;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),

      decoration: BoxDecoration(
        color: color.withOpacity(.15),

        borderRadius: BorderRadius.circular(20),
      ),

      child: Text(
        status,

        style: TextStyle(color: color, fontWeight: FontWeight.bold),
      ),
    );
  }
}
