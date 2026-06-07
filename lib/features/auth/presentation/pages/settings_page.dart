import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Settings"),
      ),

      body: const Center(
        child: Text("SettingsPage", style: TextStyle(fontSize: 22)),
      ),
    );
  }
}