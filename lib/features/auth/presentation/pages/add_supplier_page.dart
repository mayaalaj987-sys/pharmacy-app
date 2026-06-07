import 'package:flutter/material.dart';

import '../../../../core/data/supplier_data.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../data/models/supplier_model.dart';

class AddSupplierPage extends StatefulWidget {
  final SupplierModel? supplier;
  final int? index;

  const AddSupplierPage({
    super.key,
    this.supplier,
    this.index,
  });

  @override
  State<AddSupplierPage> createState() => _AddSupplierPageState();
}

class _AddSupplierPageState extends State<AddSupplierPage> {

  final nameController = TextEditingController();

  final contactPersonController = TextEditingController();

  final phoneController = TextEditingController();

  final emailController = TextEditingController();

  final addressController = TextEditingController();

  @override
  void initState() {
    super.initState();

    if (widget.supplier != null) {

      nameController.text = widget.supplier!.name;

      contactPersonController.text =
          widget.supplier!.contactPerson;

      phoneController.text =
          widget.supplier!.phone;

      emailController.text =
          widget.supplier!.email;

      addressController.text =
          widget.supplier!.address;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(

      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(60),

        child: CustomAppBar(
          title: widget.supplier == null
              ? "Add Supplier"
              : "Edit Supplier",
        ),
      ),

      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),

        child: Column(
          children: [

            CustomTextField(
              controller: nameController,
              hint: "Supplier Name",
              prefixIcon: Icons.business,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: contactPersonController,
              hint: "Contact Person",
              prefixIcon: Icons.person,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: phoneController,
              hint: "Phone Number",
              prefixIcon: Icons.phone,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: emailController,
              hint: "Email",
              prefixIcon: Icons.email,
            ),

            const SizedBox(height: 16),

            CustomTextField(
              controller: addressController,
              hint: "Address",
              prefixIcon: Icons.location_on,
            ),

            const SizedBox(height: 24),

            CustomButton(
              text: widget.supplier == null
                  ? "Add Supplier"
                  : "Update Supplier",

              onPressed: () {

                final supplier = SupplierModel(
                  name: nameController.text,
                  contactPerson: contactPersonController.text,
                  phone: phoneController.text,
                  email: emailController.text,
                  address: addressController.text,

                  medicines: [],
                );

                if (widget.index != null) {

                  suppliers[widget.index!] = supplier;

                } else {

                  suppliers.add(supplier);

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