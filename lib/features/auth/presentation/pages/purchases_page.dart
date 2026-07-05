import 'package:flutter/material.dart';

import '../../../../core/data/purchase_data.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/purchases/purchase_card.dart';
import '../../../../core/widgets/purchases/purchase_stat_card.dart';


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

    final cancelledCount = purchases
        .where((e) => e.status == "Cancelled")
        .length;

    return Scaffold(
      backgroundColor: AppColors.white,

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
                  child: PurchaseStatCard(
                    title: "Cancel",

                    value: cancelledCount.toString(),

                    color: AppColors.errorRed,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: PurchaseStatCard(
                    title: "Pending",

                    value: pendingCount.toString(),

                    color: AppColors.pendingOrange,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: PurchaseStatCard(
                    title: "Received",

                    value: receivedCount.toString(),

                    color: AppColors.lightGreen,
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
                      return PurchaseCard(
                        purchase: purchases[index],

                        onUpdate: () {
                          setState(() {});
                        },
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
