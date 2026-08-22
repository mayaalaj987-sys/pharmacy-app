import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../reports/presentation/cubit/reports_cubit.dart';
import '../../../reports/presentation/cubit/reports_state.dart';
import '../../../reports/presentation/widgets/analytics_pieces.dart';

/// Same three reasons a pharmacist picks from when booking a write-off, in
/// `lib/features/inventory/presentation/widgets/write_off_sheet.dart` — kept
/// in sync with those labels so a loss reads the same wherever it is booked
/// and wherever it is later reported. `returned_to_supplier` has no place
/// here: it is never a loss, so the server never sends a figure for it.
const _writeOffReasonLabels = <String, String>{
  'expired': 'Expired',
  'damaged': 'Damaged or spoiled',
  'lost': 'Missing at stock count',
};

/// What the pharmacy is actually doing, in one screen.
///
/// Two figures lead it and they answer different questions. Profit says whether
/// the shop traded well; cash says whether there is money in the drawer. A
/// pharmacy that spends two million on a delivery has an untouched profit and
/// an empty till, and showing only one of those is how that goes unnoticed.
///
/// Everything else qualifies those two: what was lost to expiry, how much of
/// the inventory can no longer be sold, which drugs and which shelves earn the
/// money, and in what form it arrives.
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
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: 'Analytics'),
      ),
      body: BlocBuilder<ReportsCubit, ReportsState>(
        builder: (context, state) => Column(
          children: [
            _periods(context, state),
            Expanded(child: _body(context, state)),
          ],
        ),
      ),
    );
  }

  Widget _periods(BuildContext context, ReportsState state) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: .04),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            for (final filter in ReportsCubit.revenueFilters)
              Expanded(
                child: GestureDetector(
                  key: ValueKey('analytics-period-$filter'),
                  onTap: () => context.read<ReportsCubit>().loadAnalytics(
                    filter: filter,
                  ),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    decoration: BoxDecoration(
                      color: state.analyticsFilter == filter
                          ? AppColors.white
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Text(
                      _periodLabel(filter),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: state.analyticsFilter == filter
                            ? FontWeight.bold
                            : FontWeight.normal,
                        color: state.analyticsFilter == filter
                            ? AppColors.darkGreen
                            : Colors.black54,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _periodLabel(String filter) => switch (filter) {
    'daily' => 'Today',
    'weekly' => 'Week',
    'monthly' => 'Month',
    _ => 'Year',
  };

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

    return RefreshIndicator(
      onRefresh: () => context.read<ReportsCubit>().loadAnalytics(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          _headline(state),
          _profitBreakdown(state),
          _writeOffBreakdown(state),
          AnalyticsSection(
            title: 'Revenue by period',
            child: RevenueBars(
              byPeriod: state.revenueByFilter,
              selected: state.analyticsFilter,
            ),
          ),
          AnalyticsSection(
            title: 'How customers paid',
            subtitle: 'Cash is in the drawer today. The rest settles later.',
            child: PaymentDonut(
              slices: state.paymentMethods
                  .map(
                    (method) => (
                      label: method.label,
                      share: method.share,
                      value: money(method.total),
                    ),
                  )
                  .toList(),
            ),
          ),
          AnalyticsSection(
            title: 'Top medicines',
            subtitle: 'Ranked by what they earned, not by boxes moved.',
            child: RankedBars(
              emptyMessage: 'Nothing sold in this period.',
              rows: state.mostSold
                  .take(5)
                  .map(
                    (item) => (
                      label: '${item.medicine} · ${item.totalSold} sold',
                      value: money(item.revenue),
                      weight: item.revenue,
                    ),
                  )
                  .toList(),
            ),
          ),
          AnalyticsSection(
            title: 'Revenue by category',
            child: RankedBars(
              emptyMessage: 'Nothing sold in this period.',
              rows: state.categoryRevenue
                  .map(
                    (row) => (
                      label: row.category,
                      value: money(row.revenue),
                      weight: row.revenue,
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _headline(ReportsState state) {
    final profits = state.profits;
    final cash = state.cashFlow;
    final inventory = state.inventoryValue;
    final dead = inventory.expiredCostValue;

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      childAspectRatio: 1.45,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      padding: const EdgeInsets.only(top: 12),
      children: [
        StatCard(
          key: const ValueKey('analytics-profit-card'),
          title: 'Profit',
          value: money(profits.profit),
          icon: Icons.trending_up,
          colour: analyticsPalette[0],
          alarming: profits.profit < 0,
          footnote: 'After stock, wages and losses',
        ),
        StatCard(
          key: const ValueKey('analytics-cash-card'),
          title: 'Cash in hand',
          value: money(cash.net),
          icon: Icons.account_balance_wallet_outlined,
          colour: analyticsPalette[1],
          alarming: cash.net < 0,
          // The distinction the whole screen turns on: buying stock is cash
          // gone and no cost at all, so the two figures move independently.
          footnote: cash.net < 0
              ? 'Spent more than came in'
              : 'In ${money(cash.moneyIn)} · out ${money(cash.moneyOut)}',
        ),
        StatCard(
          key: const ValueKey('analytics-revenue-card'),
          title: 'Revenue',
          value: money(profits.revenue),
          icon: Icons.receipt_long,
          colour: analyticsPalette[5],
          footnote:
              '${state.salesAverage.salesCount} sales · '
              'avg ${money(state.salesAverage.averageSale)}',
        ),
        StatCard(
          key: const ValueKey('analytics-inventory-card'),
          title: 'Inventory value',
          value: money(inventory.totalCostValue),
          icon: Icons.inventory_2_outlined,
          colour: analyticsPalette[2],
          alarming: dead > 0,
          // Without this the headline claims the pharmacy holds money the till
          // will not let it get at.
          footnote: dead > 0 ? '${money(dead)} of it expired' : 'At cost price',
        ),
      ],
    );
  }

  Widget _profitBreakdown(ReportsState state) {
    final profits = state.profits;
    final inventory = state.inventoryValue;

    return AnalyticsSection(
      title: 'Where the profit went',
      child: Column(
        children: [
          _line('Revenue', profits.revenue, analyticsPalette[1]),
          if (profits.refunds > 0)
            _line('Refunds', -profits.refunds, AppColors.pendingOrange),
          _line('Cost of goods', -profits.costOfGoods, AppColors.pendingOrange),
          _line('Salaries', -profits.salaries, AppColors.pendingOrange),
          if (profits.writeOffs > 0)
            // "Expired and damaged" undersold it: this also covers stock that
            // went missing at a count. See the breakdown below for the split.
            _line('Stock write-offs', -profits.writeOffs, AppColors.errorRed),
          const Divider(height: 22),
          _line(
            'Profit',
            profits.profit,
            profits.profit >= 0 ? AppColors.darkGreen : AppColors.errorRed,
            bold: true,
          ),
          if (inventory.expiringCostValue > 0) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.pendingOrange.withValues(alpha: .1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.schedule,
                    size: 15,
                    color: AppColors.pendingOrange,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${money(inventory.expiringCostValue)} of stock expires '
                      'within three months. Sell or discount it before it '
                      'becomes a loss.',
                      style: const TextStyle(fontSize: 11),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// What each write-off reason cost, ranked longest-first.
  ///
  /// The headline card above only ever shows one number for stock lost. That
  /// answers "how much", not "what do I do about it" — expiry means buying
  /// less or discounting sooner, damage means a handling problem, and stock
  /// missing at a count means the count itself needs a closer look. Three
  /// different fixes hiding inside one figure.
  Widget _writeOffBreakdown(ReportsState state) {
    final byReason = state.profits.writeOffsByReason;

    return AnalyticsSection(
      title: 'Why stock was written off',
      subtitle: 'What each reason cost this period.',
      child: RankedBars(
        emptyMessage: 'Nothing written off in this period.',
        rows: _writeOffReasonLabels.entries
            .map(
              (entry) => (
                label: entry.value,
                value: money(byReason[entry.key] ?? 0),
                weight: byReason[entry.key] ?? 0,
              ),
            )
            .where((row) => row.weight > 0)
            .toList(),
      ),
    );
  }

  Widget _line(String label, double value, Color colour, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: bold ? 15 : 13)),
          Text(
            money(value),
            style: TextStyle(
              color: colour,
              fontSize: bold ? 16 : 13,
              fontWeight: bold ? FontWeight.bold : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
