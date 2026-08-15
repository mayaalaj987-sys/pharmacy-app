import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class CustomTextField extends StatefulWidget {
  final TextEditingController controller;

  final String hint;

  final IconData prefixIcon;

  final bool isPassword;

  final TextInputType keyboardType;

  final int maxLines;

  const CustomTextField({
    super.key,
    required this.controller,
    required this.hint,
    required this.prefixIcon,
    this.isPassword = false,
    this.keyboardType = TextInputType.text,
    this.maxLines = 1,
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

      keyboardType: widget.keyboardType,

      maxLines: widget.isPassword ? 1 : widget.maxLines,

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

        contentPadding: EdgeInsets.symmetric(
          horizontal: 16,
          vertical: widget.maxLines > 1 ? 16 : 18,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),

        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: AppColors.borderColor),
        ),

        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: AppColors.lightGreen, width: 2),
        ),
      ),
    );
  }
}
