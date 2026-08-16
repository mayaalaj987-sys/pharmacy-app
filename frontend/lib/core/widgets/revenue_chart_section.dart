import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../features/reports/presentation/cubit/reports_cubit.dart';
import '../../features/reports/presentation/cubit/reports_state.dart';

/// Revenue per period from `GET /reports/revenue`.
///
/// The backend exposes aggregate revenue per filter (daily/weekly/monthly/
/// yearly) and has no per-day time series, so this shows those four real
/// aggregates rather than a fabricated 7-day trend line.
class RevenueChartSection extends StatelessWidget {
  const RevenueChartSection({super.key});

  static const _labels = ['Day', 'Week', 'Month', 'Year'];

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ReportsCubit, ReportsState>(
      builder: (context, state) {
        final values = ReportsCubit.revenueFilters
            .map((filter) => state.revenueByFilter[filter] ?? 0)
            .toList();

        return Container(
          height: 220,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                "Revenue by Period",
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 20),

              Expanded(child: _chart(state, values)),
            ],
          ),
        );
      },
    );
  }

  Widget _chart(ReportsState state, List<double> values) {
    if (state.status == ReportsStatus.loading ||
        state.status == ReportsStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == ReportsStatus.failure) {
      return const Center(child: Text('Revenue unavailable'));
    }

    if (values.every((value) => value == 0)) {
      return const Center(child: Text('No revenue recorded yet'));
    }

    final maxValue = values.reduce((a, b) => a > b ? a : b);

    return BarChart(
      BarChartData(
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        maxY: maxValue * 1.2,
        titlesData: FlTitlesData(
          leftTitles: const AxisTitles(
            sideTitles: SideTitles(showTitles: false),
          ),
          topTitles: const AxisTitles(
            sideTitles: SideTitles(showTitles: false),
          ),
          rightTitles: const AxisTitles(
            sideTitles: SideTitles(showTitles: false),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: 1,
              getTitlesWidget: (value, meta) {
                final index = value.toInt();
                if (index < 0 || index >= _labels.length) {
                  return const SizedBox.shrink();
                }
                return Text(
                  _labels[index],
                  style: const TextStyle(fontSize: 10),
                );
              },
            ),
          ),
        ),
        barGroups: [
          for (var i = 0; i < values.length; i++)
            BarChartGroupData(
              x: i,
              barRods: [
                BarChartRodData(
                  toY: values[i],
                  color: AppColors.lightGreen,
                  width: 22,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(6),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}
