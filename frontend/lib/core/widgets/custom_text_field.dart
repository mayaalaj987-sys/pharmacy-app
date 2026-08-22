import 'package:flutter/material.dart';

class CustomTextField extends StatefulWidget {
  final TextEditingController controller;

  final String hint;

  final IconData prefixIcon;

  final bool isPassword;

  final TextInputType keyboardType;

  final int maxLines;

  final ValueChanged<String>? onChanged;

  const CustomTextField({
    super.key,
    required this.controller,
    required this.hint,
    required this.prefixIcon,
    this.isPassword = false,
    this.keyboardType = TextInputType.text,
    this.maxLines = 1,
    this.onChanged,
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

      onChanged: widget.onChanged,

      keyboardType: widget.keyboardType,

      maxLines: widget.isPassword ? 1 : widget.maxLines,

      obscureText: widget.isPassword ? obscure : false,

      decoration: InputDecoration(
        hintText: widget.hint,
        prefixIcon: Icon(
          widget.prefixIcon,
          color: Theme.of(context).colorScheme.primary,
        ),

        suffixIcon: widget.isPassword
            ? IconButton(
                onPressed: () {
                  setState(() {
                    obscure = !obscure;
                  });
                },
                icon: Icon(
                  obscure ? Icons.visibility_off : Icons.visibility,
                  color: Theme.of(context).colorScheme.primary,
                ),
              )
            : null,

        contentPadding: EdgeInsets.symmetric(
          horizontal: 16,
          vertical: widget.maxLines > 1 ? 16 : 18,
        ),
      ),
    );
  }
}
