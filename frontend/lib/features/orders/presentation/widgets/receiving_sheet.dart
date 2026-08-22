import 'package:flutter/material.dart';

import '../../../../core/format/money.dart';
import '../../../../core/theme/app_colors.dart';
import '../../domain/receiving_plan.dart';

/// Confirms shelf prices before a delivery is taken into stock.
///
/// A delivery is the one moment the pharmacy sets its margin. The supplier's
/// cost is theirs to set; what it sells for here is not, and copying their
/// suggested retail straight onto the shelf — which is what used to happen —
/// quietly handed that decision to them.
///
/// Returns the prices to apply, keyed by catalogue medicine id, or null if the
/// pharmacist backed out.
Future<Map<int, double>?> showReceivingSheet(
  BuildContext context,
  ReceivingPlan plan,
) {
  return showModalBottomSheet<Map<int, double>>(
    context: context,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _ReceivingSheet(plan: plan),
  );
}

class _ReceivingSheet extends StatefulWidget {
  final ReceivingPlan plan;

  const _ReceivingSheet({required this.plan});

  @override
  State<_ReceivingSheet> createState() => _ReceivingSheetState();
}

class _ReceivingSheetState extends State<_ReceivingSheet> {
  late final Map<int, TextEditingController> _prices;

  @override
  void initState() {
    super.initState();
    _prices = {
      for (final item in widget.plan.items)
        item.medicineId: TextEditingController(
          text: item.suggestedSellingPrice.toStringAsFixed(0),
        ),
    };
  }

  @override
  void dispose() {
    for (final controller in _prices.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final plan = widget.plan;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Receive this delivery',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            plan.newCount > 0
                ? '${plan.newCount} of these are new to your pharmacy. Set what '
                      'you will sell them for before they go on the shelf.'
                : 'Everything here is already stocked. Prices stay as they are '
                      'unless you change them.',
            style: const TextStyle(fontSize: 12, color: Colors.black54),
          ),
          const SizedBox(height: 16),
          Flexible(
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: plan.items.length,
              separatorBuilder: (_, _) => const Divider(height: 20),
              itemBuilder: (_, index) => _line(plan.items[index]),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Not yet'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  key: const ValueKey('receive-confirm-button'),
                  onPressed: _confirm,
                  child: const Text('Add to inventory'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _line(ReceivingLine item) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                item.name,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
            if (item.isNew)
              Container(
                key: ValueKey('receive-new-${item.medicineId}'),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.lightGreen.withValues(alpha: .2),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Text('New', style: TextStyle(fontSize: 10)),
              ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          '${item.quantity} arriving · cost ${money(item.unitCost)} each',
          style: const TextStyle(fontSize: 11, color: Colors.black54),
        ),
        const SizedBox(height: 8),
        TextField(
          key: ValueKey('receive-price-${item.medicineId}'),
          controller: _prices[item.medicineId],
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            labelText: 'Sell for',
            isDense: true,
            border: const OutlineInputBorder(),
            helperText: item.currentSellingPrice == null
                ? 'Supplier suggests ${money(item.suggestedSellingPrice)}'
                : 'Currently ${money(item.currentSellingPrice!)}',
            helperStyle: const TextStyle(fontSize: 10),
          ),
        ),
      ],
    );
  }

  void _confirm() {
    // A blank or unreadable field means "leave this one alone" rather than
    // "sell it for nothing", which is the safer reading of an empty box.
    final prices = <int, double>{};

    for (final entry in _prices.entries) {
      final value = double.tryParse(entry.value.text.trim());
      if (value != null && value >= 0) prices[entry.key] = value;
    }

    Navigator.pop(context, prices);
  }
}
