import 'package:flutter/material.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/suppliers_page.dart';
import 'package:phamacy_managment/features/inventory/presentation/pages/inventory_page.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/sale_history_page.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/purchases_page.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/settings_page.dart';
import 'package:phamacy_managment/features/employees/presentation/pages/employees_page.dart';
import 'package:phamacy_managment/features/tasks/presentation/pages/tasks_page.dart';
import 'package:phamacy_managment/features/inventory/presentation/pages/write_off_history_page.dart';

import 'more_option_card.dart';

class MoreOptionsGrid extends StatelessWidget {
  const MoreOptionsGrid({super.key});

  @override
  Widget build(BuildContext context) {
    final children = <Widget>[
      // Stock grouped by shelf status (valid / expiring / expired / reorder).
      // Distinct from the Medicines tab, which is the catalogue editor.
      MoreOptionCard(
        icon: Icons.inventory_2,
        title: "Inventory",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const InventoryPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.history,
        title: "Sales History",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SaleHistoryPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.local_shipping,
        title: "Suppliers",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SuppliersPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.shopping_cart,
        title: "Purchases",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const PurchasesPage()),
          );
        },
      ),
      MoreOptionCard(
        icon: Icons.people_alt,
        title: "Employees",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const EmployeesPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.task_alt,
        title: "Tasks",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const TasksPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.remove_shopping_cart_outlined,
        title: "Stock Losses",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const WriteOffHistoryPage()),
          );
        },
      ),

      MoreOptionCard(
        icon: Icons.settings_rounded,
        title: "Setting",
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const SettingsPage()),
          );
        },
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 390
            ? 2
            : constraints.maxWidth < 760
            ? 3
            : 4;
        return GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: columns,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: constraints.maxWidth < 390 ? 1.15 : 1.05,
          children: children,
        );
      },
    );
  }
}
