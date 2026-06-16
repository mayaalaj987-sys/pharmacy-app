import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:provider/provider.dart';

import 'core/network/dio_client.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/theme/theme_provider.dart';

import 'features/auth/data/datasource/auth_api.dart';
import 'features/auth/data/datasource/auth_repository.dart';
import 'features/auth/presentation/cubit/auth_cubit.dart';
import 'features/auth/presentation/pages/logo_page.dart';
import 'features/auth/presentation/pages/main_navigation_page.dart';

void main() {
  final storage = TokenStorage();
  DioClient.init(storage);
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()),

        BlocProvider(
          create: (_) => AuthCubit(AuthRepository(AuthApi(), storage)),
        ),
      ],

      child: const MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ThemeProvider>(
      builder: (context, themeProvider, child) {
        return MaterialApp(
          debugShowCheckedModeBanner: false,

          themeMode: themeProvider.isDark ? ThemeMode.dark : ThemeMode.light,

          theme: ThemeData(brightness: Brightness.light),

          darkTheme: ThemeData(brightness: Brightness.dark),

          home: const MainNavigationPage(),
        );
      },
    );
  }
}
