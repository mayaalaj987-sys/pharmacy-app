import 'package:flutter/material.dart';

class MoreMenuBottomSheet {
  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(25),
        ),
      ),
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: GridView.count(
            shrinkWrap: true,
            crossAxisCount: 3,
            children: const [
              _MenuItem(icon: Icons.inventory, label: "Inventory"),
              _MenuItem(icon: Icons.receipt_long, label: "Sales"),
              _MenuItem(icon: Icons.local_shipping, label: "Suppliers"),
              _MenuItem(icon: Icons.shopping_bag, label: "Purchases"),
              _MenuItem(icon: Icons.settings, label: "Settings"),
              _MenuItem(icon: Icons.logout, label: "Logout"),
            ],
          ),
        );
      },
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MenuItem({
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        Navigator.pop(context);
        debugPrint("Tapped: $label");
      },
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: Colors.blue.withOpacity(0.1),
            child: Icon(icon, color: Colors.blue),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(fontSize: 12),
            textAlign: TextAlign.center,
          )
        ],
      ),
    );
  }
}