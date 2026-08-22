import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../inventory/presentation/cubit/inventory_cubit.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/medicines_page/medicine_filter_dropdown.dart';
import '../../../../core/widgets/medicines_page/medicine_list.dart';
import '../../../../core/widgets/medicines_page/medicine_search_section.dart';
import '../../../../core/widgets/medicines_page/medicine_stats_section.dart';
import 'add_medicine_page.dart';

class MedicinesPage extends StatefulWidget {
  /// Employees may view stock but cannot add or edit medicines.
  final bool readOnly;

  const MedicinesPage({super.key, this.readOnly = false});

  @override
  State<MedicinesPage> createState() => _MedicinesPageState();
}

class _MedicinesPageState extends State<MedicinesPage> {
  final searchController = TextEditingController();
  String selectedCategory = "All";
  String query = "";

  @override
  void initState() {
    super.initState();
    context.read<InventoryCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Medicines"),
      ),

      body: Column(
        children: [
          const MedicineStatsSection(),

          MedicineSearchSection(
            controller: searchController,
            onQueryChanged: (value) => setState(() => query = value),

            onAddMedicine: widget.readOnly
                ? null
                : () async {
                    final cubit = context.read<InventoryCubit>();
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const AddMedicinePage(),
                      ),
                    );

                    await cubit.load();
                  },
          ),

          MedicineFilterDropdown(
            selectedCategory: selectedCategory,
            onChanged: (value) {
              setState(() {
                selectedCategory = value;
              });
            },
          ),
          Expanded(
            child: MedicineList(
              selectedCategory: selectedCategory,
              query: query,
              readOnly: widget.readOnly,
            ),
          ),
        ],
      ),
    );
  }
}
