import 'dart:io';

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

class ProfileAvatar extends StatelessWidget {
  final String? imageUrl;
  final String? localImagePath;
  final String name;
  final double radius;

  const ProfileAvatar({
    super.key,
    required this.imageUrl,
    required this.name,
    this.localImagePath,
    this.radius = 35,
  });

  @override
  Widget build(BuildContext context) {
    final fallback = _fallback();
    final localPath = localImagePath?.trim();
    final url = imageUrl?.trim();

    Widget image;
    if (localPath != null && localPath.isNotEmpty) {
      image = Image.file(
        File(localPath),
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => fallback,
      );
    } else if (url != null && url.isNotEmpty) {
      image = Image.network(
        url,
        key: ValueKey('profile-image-$url'),
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => fallback,
        loadingBuilder: (context, child, progress) => progress == null
            ? child
            : const Center(child: CircularProgressIndicator(strokeWidth: 2)),
      );
    } else {
      image = fallback;
    }

    return ClipOval(
      child: ColoredBox(
        color: AppColors.veryLightGreen,
        child: SizedBox.square(dimension: radius * 2, child: image),
      ),
    );
  }

  Widget _fallback() {
    final trimmed = name.trim();
    return Center(
      key: const ValueKey('profile-image-fallback'),
      child: Text(
        trimmed.isEmpty ? '?' : trimmed[0].toUpperCase(),
        style: TextStyle(
          fontSize: radius * .72,
          fontWeight: FontWeight.bold,
          color: AppColors.darkGreen,
        ),
      ),
    );
  }
}
