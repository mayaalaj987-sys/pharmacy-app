import 'package:flutter/material.dart';

import '../../../../core/data/sales_data.dart';
import '../../../../core/widgets/custom_app_bar.dart';

class SaleHistoryPage extends StatefulWidget {
  const SaleHistoryPage({super.key});

  @override
  State<SaleHistoryPage> createState() => _SaleHistoryPageState();
}

class _SaleHistoryPageState extends State<SaleHistoryPage> {
  final searchController = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final filteredSales = sales.where((sale) {
      return sale.invoiceNumber.contains(searchController.text) ||
          sale.customerName.toLowerCase().contains(
            searchController.text.toLowerCase(),
          );
    }).toList();

    double totalRevenue = 0;

    for (var sale in sales) {
      totalRevenue += double.tryParse(sale.totalAmount) ?? 0;
    }

    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Sales History"),
      ),

      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),

            child: TextField(
              controller: searchController,

              decoration: InputDecoration(
                hintText: "Search invoice or customer",

                prefixIcon: const Icon(Icons.search),

                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),

              onChanged: (_) {
                setState(() {});
              },
            ),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),

            child: Row(
              children: [
                Expanded(
                  child: _buildCard(
                    "Sales",
                    sales.length.toString(),
                    Colors.blue,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: _buildCard(
                    "Revenue",
                    "\$${totalRevenue.toStringAsFixed(2)}",
                    Colors.green,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 10),

          Expanded(
            child: filteredSales.isEmpty
                ? const Center(child: Text("No Sales Found"))
                : ListView.builder(
                    itemCount: filteredSales.length,

                    itemBuilder: (context, index) {
                      final sale = filteredSales[index];

                      return Card(
                        margin: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 8,
                        ),

                        child: ListTile(
                          title: Text("Invoice #${sale.invoiceNumber}"),

                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              Text(
                                sale.customerName.isEmpty
                                    ? "Walk In Customer"
                                    : sale.customerName,
                              ),

                              Text(sale.paymentMethod),

                              Text(sale.date),
                            ],
                          ),

                          trailing: Text(
                            "\$${sale.totalAmount}",
                            style: const TextStyle(
                              color: Colors.green,
                              fontWeight: FontWeight.bold,
                            ),
                          ),

                          onTap: () {
                            showDialog(
                              context: context,

                              builder: (_) {
                                return AlertDialog(
                                  title: Text("Invoice #${sale.invoiceNumber}"),

                                  content: Column(
                                    mainAxisSize: MainAxisSize.min,

                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,

                                    children: [
                                      Text("Customer: ${sale.customerName}"),

                                      Text("Payment: ${sale.paymentMethod}"),

                                      Text("Total: \$${sale.totalAmount}"),
                                    ],
                                  ),
                                );
                              },
                            );
                          },
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildCard(String title, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),

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
}
