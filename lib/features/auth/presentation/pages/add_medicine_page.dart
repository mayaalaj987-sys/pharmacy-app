import 'package:flutter/material.dart';

import '../../../../core/data/medicine_data.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../data/models/medicine_model.dart';

class AddMedicinePage extends StatefulWidget {
  final MedicineModel? medicine;
  final int? index;

  const AddMedicinePage({super.key, this.medicine, this.index});

  @override
  State<AddMedicinePage> createState() => _AddMedicinePageState();
}

class _AddMedicinePageState extends State<AddMedicinePage> {
  final nameController = TextEditingController();

  final sellingPriceController = TextEditingController();

  final costPriceController = TextEditingController();

  final quantityController = TextEditingController();

  final expiryDateController = TextEditingController();

  final barcodeController = TextEditingController();

  final notesController = TextEditingController();

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

      sellingPriceController.text = widget.medicine!.sellingPrice;

      costPriceController.text = widget.medicine!.costPrice;

      quantityController.text = widget.medicine!.quantity;

      expiryDateController.text = widget.medicine!.expiryDate;

      barcodeController.text = widget.medicine!.barcode;

      notesController.text = widget.medicine!.notes;

      selectedCategory = widget.medicine!.category;
      manufacturerController.text = widget.medicine!.manufacturer;

      reorderLevelController.text = widget.medicine!.reorderLevel;
    }
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

            DropdownButtonFormField<String>(
              value: selectedCategory,

              decoration: InputDecoration(
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(18),
                ),
              ),

              items: categories.map((category) {
                return DropdownMenuItem(value: category, child: Text(category));
              }).toList(),


              onChanged: (value) {
                setState(() {
                  selectedCategory = value!;
                });

              },

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

            CustomTextField(
              controller: barcodeController,
              hint: "BT-2024-001",
              prefixIcon: Icons.qr_code,
            ),

            const SizedBox(height: 16),

            TextField(
              controller: notesController,

              maxLines: 4,

              decoration: InputDecoration(
                hintText: "Notes",

                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
            ),

            const SizedBox(height: 24),

            CustomButton(
              text: "Add Medicine",

              onPressed: () {
                final medicine = MedicineModel(
                  name: nameController.text,
                  category: selectedCategory,
                  manufacturer: manufacturerController.text,
                  sellingPrice: sellingPriceController.text,
                  costPrice: costPriceController.text,
                  quantity: quantityController.text,
                  reorderLevel: reorderLevelController.text,
                  expiryDate: expiryDateController.text,
                  barcode: barcodeController.text,
                  notes: notesController.text,
                );
                if (widget.index != null) {
                  medicines[widget.index!] = medicine;
                } else {
                  medicines.add(medicine);
                }

                Navigator.pop(context);
              },
            ),
          ],
        ),
      ),
    );
  }
}
