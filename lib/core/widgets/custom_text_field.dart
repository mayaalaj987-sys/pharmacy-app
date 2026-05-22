import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class CustomTextField extends StatefulWidget {
  final TextEditingController controller;

  final String hint;

  final IconData prefixIcon;

  final bool isPassword;

  const CustomTextField({
    super.key,

    required this.controller,

    required this.hint,

    required this.prefixIcon,

    this.isPassword = false,
  });

  @override
  State<CustomTextField> createState() => _CustomTextFieldState();
}

class _CustomTextFieldState extends State<CustomTextField> {
  bool obscure = true;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: widget.controller,

      obscureText: widget.isPassword ? obscure : false,

      decoration: InputDecoration(
        hintText: widget.hint,

        hintStyle: const TextStyle(color: AppColors.hintText),

        prefixIcon: Icon(widget.prefixIcon, color: AppColors.tealGreen),

        suffixIcon: widget.isPassword
            ? IconButton(
                onPressed: () {
                  setState(() {
                    obscure = !obscure;
                  });
                },

                icon: Icon(
                  obscure ? Icons.visibility_off : Icons.visibility,

                  color: AppColors.tealGreen,
                ),
              )
            : null,

        filled: true,

        fillColor: AppColors.white,

        contentPadding: const EdgeInsets.symmetric(
          vertical: 18,
          horizontal: 16,
        ),

        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),

          borderSide: BorderSide.none,
        ),

        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),

          borderSide: BorderSide(color: AppColors.borderColor),
        ),

        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),

          borderSide: const BorderSide(color: AppColors.lightGreen, width: 2),
        ),
      ),
    );
  }
}
