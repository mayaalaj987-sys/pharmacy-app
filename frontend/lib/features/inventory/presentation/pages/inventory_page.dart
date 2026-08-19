import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../domain/medicine.dart';
import '../cubit/inventory_cubit.dart';
import '../cubit/inventory_state.dart';
import '../widgets/write_off_sheet.dart';

/// Stock overview grouped by shelf status.
///
/// Reads the same `InventoryCubit` the Medicines tab uses — one request, no
/// second source of truth. Classification comes from [Medicine.status], which
/// mirrors the rules the backend enforces at sale time.
///
/// Medicines that have hit zero are absent by design: `GET /medicines` only
/// returns rows with `quantity > 0`.
class InventoryPage extends StatefulWidget {
  const InventoryPage({super.key});

  @override
  State<InventoryPage> createState() => _InventoryPageState();
}

class _InventoryPageState extends State<InventoryPage> {
  /// `null` means "everything".
  MedicineStatus? _filter;

  @override
  void initState() {
    super.initState();
    context.read<InventoryCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: 'Inventory'),
      ),
      body: BlocBuilder<InventoryCubit, InventoryState>(
        builder: (context, state) {
          if (state.status == InventoryStatus.loading ||
              state.status == InventoryStatus.initial) {
            return const Center(child: CircularProgressIndicator());
          }

          if (state.status == InventoryStatus.failure) {
            return _failure(context, state);
          }

          final all = state.medicines;
          final visible = _filter == null
              ? all
              : all.where((m) => m.status == _filter).toList();

          return RefreshIndicator(
            onRefresh: () => context.read<InventoryCubit>().load(),
            child: Column(
              children: [
                _summary(all),
                _filters(all),
                const Divider(height: 1),
                Expanded(child: _list(visible, all.isEmpty)),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _summary(List<Medicine> all) {
    final expired = all.where((m) => m.isExpired).length;
    final expiring = all.where((m) => m.isExpiringSoon).length;
    final reorder = all.where((m) => m.status == MedicineStatus.reorder).length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Row(
        children: [
          _tile('Items', all.length, AppColors.tealGreen),
          const SizedBox(width: 10),
          _tile('Expired', expired, AppColors.errorRed),
          const SizedBox(width: 10),
          _tile('Expiring', expiring, AppColors.pendingOrange),
          const SizedBox(width: 10),
          _tile('Reorder', reorder, Colors.blue),
        ],
      ),
    );
  }

  Widget _tile(String label, int value, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .12),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          children: [
            Text(
              '$value',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const SizedBox(height: 2),
            Text(label, style: const TextStyle(fontSize: 12)),
          ],
        ),
      ),
    );
  }

  Widget _filters(List<Medicine> all) {
    int count(MedicineStatus status) =>
        all.where((m) => m.status == status).length;

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: [
          _chip('All', all.length, null),
          for (final status in const [
            MedicineStatus.healthy,
            MedicineStatus.expiringSoon,
            MedicineStatus.expired,
            MedicineStatus.reorder,
          ])
            _chip(status.label, count(status), status),
        ],
      ),
    );
  }

  Widget _chip(String label, int count, MedicineStatus? status) {
    final selected = _filter == status;

    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        key: ValueKey('inventory-filter-${status?.name ?? 'all'}'),
        label: Text('$label ($count)'),
        selected: selected,
        onSelected: (_) => setState(() => _filter = status),
        selectedColor: AppColors.lightGreen,
      ),
    );
  }

  Widget _list(List<Medicine> medicines, bool inventoryEmpty) {
    if (medicines.isEmpty) {
      return ListView(
        children: [
          Padding(
            padding: const EdgeInsets.all(32),
            child: Text(
              inventoryEmpty
                  ? 'No stock yet. Order from a supplier under More → '
                        'Purchases, then receive the order to fill your shelves.'
                  : 'Nothing in this category.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey),
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: medicines.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (context, index) => _row(medicines[index]),
    );
  }

  Widget _row(Medicine medicine) {
    final status = medicine.status;
    final color = _statusColor(status);
    final days = medicine.daysUntilExpiry;

    return ListTile(
      key: ValueKey('inventory-row-${medicine.id}'),
      // Stock that expired or broke has to leave the shelf somehow. Editing the
      // quantity down was the only way and it recorded nothing — not what
      // happened, not what it cost.
      onTap: medicine.quantity > 0
          ? () => showWriteOffSheet(context, medicine)
          : null,
      title: Text(
        medicine.name,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 2),
          Text('${medicine.category} • ${money(medicine.sellingPrice)}'),
          Text(
            _expiryLine(medicine, days),
            style: TextStyle(
              color: status == MedicineStatus.expired
                  ? AppColors.errorRed
                  : status == MedicineStatus.expiringSoon
                  ? AppColors.pendingOrange
                  : Colors.grey,
            ),
          ),
        ],
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: color.withValues(alpha: .15),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              status.label,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Qty ${medicine.quantity}',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  String _expiryLine(Medicine medicine, int? days) {
    final expiry = medicine.expireDate;
    if (expiry == null) return 'No expiry recorded';

    final date =
        '${expiry.year.toString().padLeft(4, '0')}-'
        '${expiry.month.toString().padLeft(2, '0')}-'
        '${expiry.day.toString().padLeft(2, '0')}';

    if (days == null) return 'Expires $date';
    if (days < 0) return 'Expired $date (${days.abs()} days ago)';
    if (days == 0) return 'Expires today ($date)';

    return 'Expires $date (in $days days)';
  }

  Color _statusColor(MedicineStatus status) => switch (status) {
    MedicineStatus.expired => AppColors.errorRed,
    MedicineStatus.outOfStock => Colors.grey,
    MedicineStatus.expiringSoon => AppColors.pendingOrange,
    MedicineStatus.reorder => Colors.blue,
    MedicineStatus.healthy => AppColors.successGreen,
  };

  Widget _failure(BuildContext context, InventoryState state) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              userFacingError(
                state.error,
                fallback: 'Unable to load inventory.',
              ),
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.errorRed),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              key: const ValueKey('inventory-retry-button'),
              onPressed: () => context.read<InventoryCubit>().load(),
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
