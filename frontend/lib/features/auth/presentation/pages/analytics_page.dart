import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../reports/presentation/cubit/reports_cubit.dart';
import '../../../reports/presentation/cubit/reports_state.dart';
import '../../../../core/format/money.dart';

class AnalyticsPage extends StatefulWidget {
  const AnalyticsPage({super.key});

  @override
  State<AnalyticsPage> createState() => _AnalyticsPageState();
}

class _AnalyticsPageState extends State<AnalyticsPage> {
  @override
  void initState() {
    super.initState();
    context.read<ReportsCubit>().loadAnalytics();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Analytics"),
      ),
      body: BlocBuilder<ReportsCubit, ReportsState>(
        builder: (context, state) {
          return Column(
            children: [
              _filters(context, state),
              Expanded(child: _body(context, state)),
            ],
          );
        },
      ),
    );
  }

  Widget _filters(BuildContext context, ReportsState state) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          for (final filter in ReportsCubit.revenueFilters)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text(filter[0].toUpperCase() + filter.substring(1)),
                selected: state.analyticsFilter == filter,
                onSelected: (_) =>
                    context.read<ReportsCubit>().loadAnalytics(filter: filter),
              ),
            ),
        ],
      ),
    );
  }

  Widget _body(BuildContext context, ReportsState state) {
    if (state.analyticsStatus == ReportsStatus.loading ||
        state.analyticsStatus == ReportsStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.analyticsStatus == ReportsStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(
                  state.analyticsError,
                  fallback: 'Unable to load analytics.',
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('analytics-retry-button'),
                onPressed: () => context.read<ReportsCubit>().loadAnalytics(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final profits = state.profits;
    final inventory = state.inventoryValue;

    return RefreshIndicator(
      onRefresh: () => context.read<ReportsCubit>().loadAnalytics(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        children: [
          const Text(
            "Profit breakdown",
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          _row("Revenue", profits.revenue, Colors.blue),
          _row("Cost of goods", profits.costOfGoods, AppColors.pendingOrange),
          _row("Salaries", profits.salaries, AppColors.pendingOrange),
          const Divider(height: 24),
          _row(
            "Profit",
            profits.profit,
            profits.profit >= 0 ? AppColors.lightGreen : AppColors.errorRed,
            bold: true,
          ),

          const SizedBox(height: 24),
          const Text(
            "Inventory value",
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          _row("At cost", inventory.totalCostValue, Colors.blueGrey),
          _row("At selling price", inventory.totalSellingValue, Colors.teal),

          const SizedBox(height: 24),
          const Text(
            "Most sold medicines",
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          if (state.mostSold.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text("No sales in this period"),
            )
          else
            ...state.mostSold.map(
              (item) => ListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(item.medicine),
                subtitle: Text(item.category),
                trailing: Text(
                  "${item.totalSold} sold",
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _row(String label, double value, Color color, {bool bold = false}) {
    final style = TextStyle(
      color: color,
      fontWeight: bold ? FontWeight.bold : FontWeight.w600,
      fontSize: bold ? 16 : 14,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: bold ? 16 : 14)),
          Text(money(value), style: style),
        ],
      ),
    );
  }
}
