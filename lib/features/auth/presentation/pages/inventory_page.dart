import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/inventory/inventory_filter_tabs.dart';
import '../../../../core/widgets/inventory/inventory_list.dart';
import '../../../../core/widgets/inventory/inventory_search_section.dart';
import '../../../../core/widgets/inventory/inventory_stats_section.dart';

class InventoryPage extends StatefulWidget {
  const InventoryPage({super.key});

  @override
  State<InventoryPage> createState() => _InventoryPageState();
}


class _InventoryPageState extends State<InventoryPage> {
  final searchController = TextEditingController();

  String selectedFilter = "All";

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Inventory"),
      ),

      body: Column(
        children: [
          const InventoryStatsSection(),

          InventoryFilterTabs(
            selectedFilter: selectedFilter,

            onChanged: (value) {
              setState(() {
                selectedFilter = value;
              });
            },
          ),

          InventorySearchSection(controller: searchController),

          Expanded(
            child: InventoryList(
              search: searchController.text,
              filter: selectedFilter,
            ),
          ),
        ],
      ),
    );
  }
}
