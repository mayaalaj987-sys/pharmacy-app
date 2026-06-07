import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/medicines_page/medicine_filter_dropdown.dart';
import '../../../../core/widgets/medicines_page/medicine_list.dart';
import '../../../../core/widgets/medicines_page/medicine_search_section.dart';
import '../../../../core/widgets/medicines_page/medicine_stats_section.dart';
import 'add_medicine_page.dart';

class MedicinesPage extends StatefulWidget {
  const MedicinesPage({super.key});

  @override
  State<MedicinesPage> createState() => _MedicinesPageState();
}

class _MedicinesPageState extends State<MedicinesPage> {
  final searchController = TextEditingController();
  String selectedCategory = "All";
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: "Medicines",
        ),
      ),

      body: Column(
        children: [
          const MedicineStatsSection(),

          MedicineSearchSection(
            controller: searchController,

            onAddMedicine: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const AddMedicinePage(),
                ),
              );

              setState(() {});
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
            ),
          ),
        ],
      ),
    );
  }
}