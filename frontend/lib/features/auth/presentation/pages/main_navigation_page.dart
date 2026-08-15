import 'package:flutter/material.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/pos_page.dart';

import '../../../../core/theme/app_colors.dart';


import 'analytics_page.dart';
import 'home_page.dart';
import 'medicine_page.dart';
import 'more_page.dart';

class MainNavigationPage extends StatefulWidget {
  const MainNavigationPage({super.key});

  @override
  State<MainNavigationPage> createState() => _MainNavigationPageState();
}

class _MainNavigationPageState extends State<MainNavigationPage> {
  int currentIndex = 0;

  final List<Widget> pages = [
    const HomePage(),

    const MedicinesPage(),

    const PosPage(),

    const AnalyticsPage(),

    const MorePage(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: pages[currentIndex],

      bottomNavigationBar: BottomNavigationBar(
        currentIndex: currentIndex,

        onTap: (index) {
          setState(() {
            currentIndex = index;
          });
        },

        type: BottomNavigationBarType.fixed,

        backgroundColor: Colors.white,

        selectedItemColor: AppColors.darkGreen,

        unselectedItemColor: Colors.grey,

        selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold),

        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),

          BottomNavigationBarItem(
            icon: Icon(Icons.medication),

            label: 'Medicines',
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.point_of_sale),

            label: 'POS',
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.bar_chart),

            label: 'Analytics',
          ),

          BottomNavigationBarItem(icon: Icon(Icons.menu), label: 'More'),
        ],
      ),
    );
  }
}
