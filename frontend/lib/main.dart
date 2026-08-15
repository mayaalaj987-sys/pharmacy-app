import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:provider/provider.dart';

import 'core/network/auth_session_events.dart';
import 'core/network/dio_client.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/theme/theme_provider.dart';
import 'features/auth/data/datasource/auth_api.dart';
import 'features/auth/data/datasource/auth_repository.dart';
import 'features/auth/presentation/cubit/auth_cubit.dart';
import 'features/auth/presentation/cubit/auth_state.dart';
import 'features/auth/presentation/pages/auth_gate.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final storage = TokenStorage();
  final sessionEvents = AuthSessionEvents();
  DioClient.init(storage, sessionEvents);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        BlocProvider(
          create: (_) =>
              AuthCubit(AuthRepository(AuthApi(), storage, sessionEvents))
                ..restoreSession(),
        ),
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();

  @override
  Widget build(BuildContext context) {
    return Consumer<ThemeProvider>(
      builder: (context, themeProvider, child) {
        return BlocListener<AuthCubit, AuthState>(
          listenWhen: (previous, current) =>
              current is AuthAuthenticated ||
              current is AuthPharmacySelectionRequired ||
              current is AuthAccessRestricted ||
              current is AuthUnauthenticated,
          listener: (context, state) {
            final navigator = _navigatorKey.currentState;
            if (navigator != null && navigator.canPop()) {
              navigator.popUntil((route) => route.isFirst);
            }
          },
          child: MaterialApp(
            navigatorKey: _navigatorKey,
            debugShowCheckedModeBanner: false,
            themeMode: themeProvider.isDark ? ThemeMode.dark : ThemeMode.light,
            theme: ThemeData(brightness: Brightness.light),
            darkTheme: ThemeData(brightness: Brightness.dark),
            home: const AuthGate(),
          ),
        );
      },
    );
  }
}
