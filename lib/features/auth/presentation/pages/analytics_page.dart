import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';

class AnalyticsPage extends StatelessWidget {
  const AnalyticsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Color(0xFFF2F0EF),
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "analytics"),
      ),
    );
  }
}
