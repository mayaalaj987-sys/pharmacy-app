import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/purchases/purchase_card.dart';
import '../../../../core/widgets/purchases/purchase_stat_card.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../orders/presentation/cubit/orders_cubit.dart';
import '../../../orders/presentation/cubit/orders_state.dart';

class PurchasesPage extends StatefulWidget {
  const PurchasesPage({super.key});

  @override
  State<PurchasesPage> createState() => _PurchasesPageState();
}

class _PurchasesPageState extends State<PurchasesPage> {
  @override
  void initState() {
    super.initState();
    context.read<OrdersCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),

        child: CustomAppBar(title: "Purchases"),
      ),

      body: BlocBuilder<OrdersCubit, OrdersState>(
        builder: (context, state) {
          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),

                child: Row(
                  children: [
                    Expanded(
                      child: PurchaseStatCard(
                        title: "Cancel",
                        value: state.countByStatus('cancelled').toString(),
                        color: AppColors.errorRed,
                      ),
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: PurchaseStatCard(
                        title: "Pending",
                        value: state.countByStatus('pending').toString(),
                        color: AppColors.pendingOrange,
                      ),
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: PurchaseStatCard(
                        title: "Received",
                        value: state.countByStatus('received').toString(),
                        color: AppColors.lightGreen,
                      ),
                    ),
                  ],
                ),
              ),

              Expanded(child: _body(context, state)),
            ],
          );
        },
      ),
    );
  }

  Widget _body(BuildContext context, OrdersState state) {
    if (state.status == OrdersStatus.loading ||
        state.status == OrdersStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == OrdersStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(
                  state.error,
                  fallback: 'Unable to load orders.',
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('orders-retry-button'),
                onPressed: () => context.read<OrdersCubit>().load(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (state.orders.isEmpty) {
      return const Center(child: Text("No purchase orders"));
    }

    return RefreshIndicator(
      onRefresh: () => context.read<OrdersCubit>().load(),
      child: ListView.builder(
        itemCount: state.orders.length,
        itemBuilder: (context, index) {
          return PurchaseCard(purchase: state.orders[index]);
        },
      ),
    );
  }
}
