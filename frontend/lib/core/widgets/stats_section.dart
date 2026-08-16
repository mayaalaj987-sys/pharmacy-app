import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../features/reports/presentation/cubit/reports_cubit.dart';
import '../../features/reports/presentation/cubit/reports_state.dart';
import '../theme/app_colors.dart';

class StatsSection extends StatelessWidget {
  const StatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ReportsCubit, ReportsState>(
      builder: (context, state) {
        if (state.status == ReportsStatus.loading ||
            state.status == ReportsStatus.initial) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: CircularProgressIndicator()),
          );
        }

        if (state.status == ReportsStatus.failure) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              children: [
                Text(
                  state.error?.message ?? 'Unable to load dashboard.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.errorRed),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  key: const ValueKey('dashboard-retry-button'),
                  onPressed: () => context.read<ReportsCubit>().loadDashboard(),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Retry'),
                ),
              ],
            ),
          );
        }

        final dashboard = state.dashboard;

        return GridView.count(
          shrinkWrap: true,
          crossAxisCount: 2,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 1.5,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,

          children: [
            _StatCard(
              title: "Today Sales",
              value: dashboard.todaySalesCount.toString(),
              icon: Icons.shopping_cart,
              color: AppColors.lightGreen,
              percent: "Orders",
            ),

            _StatCard(
              title: "Revenue",
              value: "\$${dashboard.todayRevenue.toStringAsFixed(2)}",
              icon: Icons.attach_money,
              color: Colors.blue,
              percent: "Today",
            ),

            _StatCard(
              title: "Low Stock",
              value: dashboard.lowStockCount.toString(),
              icon: Icons.warning,
              color: AppColors.warningYellow,
              percent: "Need Restock",
            ),

            _StatCard(
              title: "Expiring",
              value: dashboard.expiringCount.toString(),
              icon: Icons.error,
              color: AppColors.errorRed,
              percent: "Within 3 Months",
            ),
          ],
        );
      },
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final String percent;

  const _StatCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    required this.percent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),

      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Icon(icon, color: color),

          const Spacer(),

          Text(
            value,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),

          Text(title),

          Text(percent, style: TextStyle(color: color, fontSize: 12)),
        ],
      ),
    );
  }
}
