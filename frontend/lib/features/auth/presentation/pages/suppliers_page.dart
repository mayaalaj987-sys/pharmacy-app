import 'package:flutter/material.dart';

import '../../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/suppliers/supplier_list.dart';
import 'add_supplier_page.dart';

class SuppliersPage extends StatefulWidget {
  const SuppliersPage({super.key});

  @override
  State<SuppliersPage> createState() => _SuppliersPageState();
}

class _SuppliersPageState extends State<SuppliersPage> {

  Future<void> onAddSupplierPage() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => const AddSupplierPage(),
      ),
    );

    setState(() {});
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: "Suppliers",
        ),
      ),

      floatingActionButton: GestureDetector(
        onTap: onAddSupplierPage,

        child: Container(
          width: 50,
          height: 50,

          decoration: BoxDecoration(
            color: AppColors.darkGreen,
            borderRadius: BorderRadius.circular(16),
          ),

          child: const Icon(
            Icons.add,
            color: Colors.white,
          ),
        ),
      ),

      body: const SupplierList(),
    );
  }
}