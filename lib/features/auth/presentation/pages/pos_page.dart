import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/widgets/pos_page/pos_empty_cart.dart';
import '../../../../core/widgets/pos_page/pos_payment_methods.dart';
import '../../../../core/widgets/pos_page/pos_search_section.dart';
import '../../../../core/widgets/pos_page/pos_total_section.dart';

class PosPage extends StatefulWidget {
  const PosPage({super.key});

  @override
  State<PosPage> createState() => _PosPageState();
}

class _PosPageState extends State<PosPage> {
  final searchController = TextEditingController();

  final customerController = TextEditingController();
  String selectedPayment = 'Cash';

  @override
  void dispose() {
    searchController.dispose();
    customerController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {

    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Point of Sale"),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            children: [
              const Divider(height: 1),

              PosSearchSection(controller: searchController),

              const PosEmptyCart(),

              Container(
                padding: const EdgeInsets.all(20),

                decoration: BoxDecoration(
                  color: Colors.grey.shade50,

                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(30),
                  ),
                ),

                child: Column(
                  children: [
                    CustomTextField(
                      controller: customerController,
                      hint: "Customer Name (Optional)",
                      prefixIcon: Icons.person,
                    ),

                    const SizedBox(height: 18),

                    PosPaymentMethods(
                      selectedPayment: selectedPayment,
                      onChanged: (value) {
                        setState(() {
                          selectedPayment = value;
                        });
                      },
                    ),

                    const SizedBox(height: 22),

                    const PosTotalSection(),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
