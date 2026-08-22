import 'package:flutter/material.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/more_page/more_options_grid.dart';

class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "More Options"),
      ),

      body: SingleChildScrollView(
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1000),
            child: const Padding(
              padding: EdgeInsets.all(16),
              child: Column(
                children: [SizedBox(height: 16), MoreOptionsGrid()],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
