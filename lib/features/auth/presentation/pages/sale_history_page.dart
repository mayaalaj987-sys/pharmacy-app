import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';

class SaleHistoryPage extends StatelessWidget {
  const SaleHistoryPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "SaleHistory"),
      ),

      body: const Center(
        child: Text("SaleHistoryPage", style: TextStyle(fontSize: 22)),
      ),
    );
  }
}
