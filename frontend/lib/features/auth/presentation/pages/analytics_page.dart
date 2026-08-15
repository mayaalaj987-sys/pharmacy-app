import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../../core/widgets/custom_app_bar.dart';

class AnalyticsPage extends StatelessWidget {
  const AnalyticsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "analytics"),
      ),
    );
  }
}
