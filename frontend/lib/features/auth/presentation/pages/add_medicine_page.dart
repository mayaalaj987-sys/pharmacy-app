import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../inventory/domain/medicine.dart';
import '../../../inventory/presentation/cubit/inventory_cubit.dart';
import '../../../inventory/presentation/cubit/inventory_state.dart';

class AddMedicinePage extends StatefulWidget {
  final Medicine? medicine;

  const AddMedicinePage({super.key, this.medicine});

  @override
  State<AddMedicinePage> createState() => _AddMedicinePageState();
}

class _AddMedicinePageState extends State<AddMedicinePage> {
  final nameController = TextEditingController();

  final sellingPriceController = TextEditingController();

  final costPriceController = TextEditingController();

  final quantityController = TextEditingController();

  final expiryDateController = TextEditingController();

  String selectedCategory = "Antibiotics";
  final manufacturerController = TextEditingController();

  final reorderLevelController = TextEditingController();

  final List<String> categories = [
    "Antibiotics",
    "Painkillers",
    "Vitamins",
    "Gastrointestinal",
    "Respiratory",
    "Cardiovascular",
    "Dermatology",
  ];

  @override
  void initState() {
    super.initState();

    if (widget.medicine != null) {
      nameController.text = widget.medicine!.name;

      sellingPriceController.text = widget.medicine!.sellingPrice.toString();

      costPriceController.text = widget.medicine!.costPrice.toString();

      quantityController.text = widget.medicine!.quantity.toString();

      final expiry = widget.medicine!.expireDate;
      expiryDateController.text = expiry == null
          ? ''
          : '${expiry.year.toString().padLeft(4, '0')}-'
                '${expiry.month.toString().padLeft(2, '0')}-'
                '${expiry.day.toString().padLeft(2, '0')}';

      selectedCategory = widget.medicine!.category;
      manufacturerController.text = widget.medicine!.manufacturer;

      reorderLevelController.text =
          widget.medicine!.reorderLevel?.toString() ?? '';
    }
  }

  Future<void> _save() async {
    final name = nameController.text.trim();
    final selling = double.tryParse(sellingPriceController.text.trim());
    final cost = double.tryParse(costPriceController.text.trim());
    final quantity = int.tryParse(quantityController.text.trim());
    final expiry = expiryDateController.text.trim();
    final manufacturer = manufacturerController.text.trim();

    // Mirrors the backend contract so obvious errors are caught before the call.
    if (name.isEmpty ||
        manufacturer.isEmpty ||
        selling == null ||
        cost == null ||
        quantity == null ||
        expiry.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Name, manufacturer, numeric prices/quantity and expiry date are required.',
          ),
        ),
      );
      return;
    }

    final payload = <String, dynamic>{
      'name': name,
      'category_medicine': selectedCategory,
      'manufacturer': manufacturer,
      'selling_price': selling,
      'cost_price': cost,
      'quantity': quantity,
      'expire_date': expiry,
      if (reorderLevelController.text.trim().isNotEmpty)
        'reorder_level': int.tryParse(reorderLevelController.text.trim()),
    };

    final cubit = context.read<InventoryCubit>();
    final existing = widget.medicine;
    final success = existing == null
        ? await cubit.addMedicine(payload)
        : await cubit.editMedicine(existing.id, payload);

    if (success && mounted) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: widget.medicine == null ? "Add Medicine" : "Edit Medicine",
        ),
      ),

      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),

        child: Column(
          children: [
            CustomTextField(
              controller: nameController,
              hint: "Medicine Name",
              prefixIcon: Icons.medication,
            ),

            const SizedBox(height: 16),

            Container(
              decoration: BoxDecoration(
                color: Colors.white,

                borderRadius: BorderRadius.circular(18),

                border: Border.all(color: AppColors.lightGreen, width: 1.5),

                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),

              child: DropdownButtonFormField<String>(
                value: selectedCategory,

                decoration: const InputDecoration(
                  border: InputBorder.none,

                  contentPadding: EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                ),

                icon: const Icon(
                  Icons.keyboard_arrow_down_rounded,
                  color: AppColors.darkGreen,
                ),

                dropdownColor: Colors.white,

                style: const TextStyle(
                  color: AppColors.darkGreen,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),

                items: categories.map((category) {
                  return DropdownMenuItem(
                    value: category,
                    child: Text(category),
                  );
                }).toList(),

                onChanged: (value) {
                  setState(() {
                    selectedCategory = value!;
                  });
                },
              ),
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: manufacturerController,
              hint: "Manufacturer",
              prefixIcon: Icons.factory,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: sellingPriceController,
              hint: "Selling Price",
              prefixIcon: Icons.attach_money,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: costPriceController,
              hint: "Cost Price",
              prefixIcon: Icons.money,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: quantityController,
              hint: "Quantity",
              prefixIcon: Icons.inventory,
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: reorderLevelController,
              hint: "Reorder Level",
              prefixIcon: Icons.warning_amber,
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: expiryDateController,
              hint: "Expiry Date",
              prefixIcon: Icons.calendar_month,
            ),

            const SizedBox(height: 16),

            const SizedBox(height: 24),

            BlocBuilder<InventoryCubit, InventoryState>(
              builder: (context, state) {
                return Column(
                  children: [
                    if (state.error != null) ...[
                      Text(
                        state.error!.message,
                        key: const ValueKey('medicine-error'),
                        style: const TextStyle(color: AppColors.errorRed),
                      ),
                      const SizedBox(height: 12),
                    ],
                    CustomButton(
                      text: widget.medicine == null
                          ? "Add Medicine"
                          : "Save Changes",
                      isLoading: state.saving,
                      onPressed: state.saving ? null : _save,
                    ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
