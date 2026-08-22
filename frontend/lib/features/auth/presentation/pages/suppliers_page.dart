import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../suppliers/presentation/cubit/suppliers_cubit.dart';

import '../../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/purchases/cart_fab.dart';
import '../../../../core/widgets/suppliers/supplier_list.dart';
import '../../../purchase_cart/presentation/cubit/purchase_cart_cubit.dart';

class SuppliersPage extends StatefulWidget {
  const SuppliersPage({super.key});

  @override
  State<SuppliersPage> createState() => _SuppliersPageState();
}

class _SuppliersPageState extends State<SuppliersPage> {
  @override
  void initState() {
    super.initState();
    context.read<SuppliersCubit>().load();
    // So the cart's count is right the moment this screen appears: the app
    // may have queued a restock since it was last opened.
    context.read<PurchaseCartCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Suppliers"),
      ),

      body: const SupplierList(),

      floatingActionButton: const PurchaseCartFab(),
    );
  }
}
