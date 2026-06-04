import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import 'package:phamacy_managment/core/widgets/custom_app_bar.dart';
import 'package:phamacy_managment/core/widgets/dashboard_greeting.dart';
import 'package:phamacy_managment/core/widgets/stats_section.dart';
import 'package:phamacy_managment/core/widgets/quick_actions_section.dart';
import 'package:phamacy_managment/core/widgets/revenue_chart_section.dart';
import 'package:phamacy_managment/core/widgets/recent_sales_section.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.white,

      appBar: PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Home"),
      ),

      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            DashboardGreeting(),

            SizedBox(height: 20),

            StatsSection(),

            SizedBox(height: 20),

            QuickActionsSection(),

            SizedBox(height: 20),

            RevenueChartSection(),

            SizedBox(height: 20),

            RecentSalesSection(),
          ],
        ),
      ),
    );
  }
}