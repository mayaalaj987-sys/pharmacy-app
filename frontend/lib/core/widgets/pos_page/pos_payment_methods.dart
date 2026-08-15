import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

class PosPaymentMethods extends StatelessWidget {
  final String selectedPayment;

  final Function(String) onChanged;

  const PosPaymentMethods({
    super.key,
    required this.selectedPayment,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _PaymentButton(
            title: "Insurance",
            icon: Icons.verified_user,
            selected: selectedPayment == "Insurance",
            onPressed: () {
              onChanged("Insurance");
            },
          ),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: _PaymentButton(
            title: "Card",
            icon: Icons.credit_card,
            selected: selectedPayment == "Card",
            onPressed: () {
              onChanged("Card");
            },
          ),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: _PaymentButton(
            title: "Cash",
            icon: Icons.payments_outlined,
            selected: selectedPayment == "Cash",
            onPressed: () {
              onChanged("Cash");
            },
          ),
        ),
      ],
    );
  }
}

class _PaymentButton extends StatelessWidget {
  final String title;

  final IconData icon;

  final bool selected;

  final VoidCallback onPressed;

  const _PaymentButton({
    required this.title,
    required this.icon,
    required this.selected,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 55,

      child: ElevatedButton(
        onPressed: onPressed,

        style: ElevatedButton.styleFrom(
          elevation: 0,

          backgroundColor:
          selected ? AppColors.lightGreen : Colors.white,

          foregroundColor:
          selected ? Colors.white : Colors.black87,

          side: BorderSide(
            color: selected
                ? AppColors.lightGreen
                : Colors.grey.shade300,
          ),

          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),

        child: FittedBox(
          fit: BoxFit.scaleDown,

          child: Row(
            mainAxisSize: MainAxisSize.min,

            children: [
              Icon(icon),

              const SizedBox(width: 6),

              Text(
                title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}