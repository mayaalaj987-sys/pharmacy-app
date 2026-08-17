import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../sales/domain/sale.dart';
import '../../../sales/presentation/cubit/sales_cubit.dart';
import '../../../sales/presentation/cubit/sales_state.dart';
import '../../../../core/format/money.dart';

class SaleHistoryPage extends StatefulWidget {
  const SaleHistoryPage({super.key});

  @override
  State<SaleHistoryPage> createState() => _SaleHistoryPageState();
}

class _SaleHistoryPageState extends State<SaleHistoryPage> {
  final searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    context.read<SalesCubit>().load();
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Sales History"),
      ),

      body: BlocBuilder<SalesCubit, SalesState>(
        builder: (context, state) {
          final query = searchController.text.trim().toLowerCase();
          final filteredSales = state.sales.where((sale) {
            if (query.isEmpty) return true;
            return sale.id.toString().contains(query) ||
                (sale.customerName ?? '').toLowerCase().contains(query);
          }).toList();

          return Column(
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
                        state.totalSales.toString(),
                        Colors.blue,
                      ),
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: _buildCard(
                        "Revenue",
                        money(state.totalPrice),
                        Colors.green,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 10),

              Expanded(child: _list(context, state, filteredSales)),
            ],
          );
        },
      ),
    );
  }

  Widget _list(BuildContext context, SalesState state, List<Sale> sales) {
    if (state.status == SalesStatus.loading ||
        state.status == SalesStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == SalesStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(state.error, fallback: 'Unable to load sales.'),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('sales-retry-button'),
                onPressed: () => context.read<SalesCubit>().load(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (sales.isEmpty) {
      return const Center(child: Text("No Sales Found"));
    }

    return RefreshIndicator(
      onRefresh: () => context.read<SalesCubit>().load(),
      child: ListView.builder(
        itemCount: sales.length,

        itemBuilder: (context, index) {
          final sale = sales[index];

          return Card(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),

            child: ListTile(
              title: Text("Invoice #${sale.id}"),

              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Text(sale.customerName ?? "Walk In Customer"),

                  Text(sale.paymentLabel),

                  Text(_formatDate(sale.date)),
                ],
              ),

              trailing: Text(
                money(sale.totalPrice),
                style: const TextStyle(
                  color: Colors.green,
                  fontWeight: FontWeight.bold,
                ),
              ),

              onTap: () => _showInvoice(context, sale),
            ),
          );
        },
      ),
    );
  }

  void _showInvoice(BuildContext context, Sale sale) {
    showDialog<void>(
      context: context,

      builder: (_) {
        return AlertDialog(
          title: Text("Invoice #${sale.id}"),

          content: Column(
            mainAxisSize: MainAxisSize.min,

            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text("Customer: ${sale.customerName ?? 'Walk In Customer'}"),

              Text("Payment: ${sale.paymentLabel}"),

              Text("Date: ${_formatDate(sale.date)}"),

              const Divider(height: 20),

              ...sale.items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    "${item.medicineName} x${item.quantity} - "
                    "${money(item.lineTotal)}",
                  ),
                ),
              ),

              const Divider(height: 20),

              Text(
                "Total: ${money(sale.totalPrice)}",
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ],
          ),
        );
      },
    );
  }

  String _formatDate(DateTime? date) {
    if (date == null) return '-';
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  Widget _buildCard(String title, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),

      decoration: BoxDecoration(
        color: color.withValues(alpha: .15),

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
