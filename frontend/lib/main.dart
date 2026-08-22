import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:provider/provider.dart';

import 'core/network/auth_session_events.dart';
import 'core/network/dio_client.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_provider.dart';
import 'features/auth/data/datasource/auth_api.dart';
import 'features/auth/data/datasource/auth_repository.dart';
import 'features/auth/presentation/cubit/auth_cubit.dart';
import 'features/auth/presentation/cubit/auth_state.dart';
import 'core/widgets/app_startup_gate.dart';
import 'features/account/data/account_api.dart';
import 'features/account/data/account_repository.dart';
import 'features/account/presentation/cubit/account_cubit.dart';
import 'features/inventory/data/inventory_api.dart';
import 'features/inventory/data/inventory_repository.dart';
import 'features/inventory/presentation/cubit/inventory_cubit.dart';
import 'features/suppliers/data/suppliers_api.dart';
import 'features/suppliers/data/suppliers_repository.dart';
import 'features/suppliers/presentation/cubit/suppliers_cubit.dart';
import 'features/orders/data/orders_api.dart';
import 'features/orders/data/orders_repository.dart';
import 'features/orders/presentation/cubit/orders_cubit.dart';
import 'features/purchase_cart/data/purchase_cart_api.dart';
import 'features/purchase_cart/data/purchase_cart_repository.dart';
import 'features/purchase_cart/presentation/cubit/purchase_cart_cubit.dart';
import 'features/sales/data/sales_api.dart';
import 'features/sales/data/sales_repository.dart';
import 'features/sales/presentation/cubit/sales_cubit.dart';
import 'features/reports/data/reports_api.dart';
import 'features/reports/data/reports_repository.dart';
import 'features/reports/presentation/cubit/reports_cubit.dart';
import 'features/employees/data/employees_api.dart';
import 'features/employees/data/employees_repository.dart';
import 'features/employees/presentation/cubit/employees_cubit.dart';
import 'features/employee_workspace/data/employee_workspace_api.dart';
import 'features/employee_workspace/data/employee_workspace_repository.dart';
import 'features/employee_workspace/presentation/cubit/employee_workspace_cubit.dart';
import 'features/tasks/data/tasks_api.dart';
import 'features/tasks/data/tasks_repository.dart';
import 'features/tasks/presentation/cubit/tasks_cubit.dart';
import 'features/notifications/data/notifications_api.dart';
import 'features/notifications/data/notifications_repository.dart';
import 'features/notifications/presentation/cubit/notifications_cubit.dart';
import 'features/rating/data/rating_api.dart';
import 'features/employee_offers/data/employee_offers_api.dart';
import 'features/employee_offers/data/employee_offers_repository.dart';
import 'features/employee_offers/presentation/cubit/employee_offers_cubit.dart';
import 'features/support/data/support_api.dart';
import 'features/support/data/support_repository.dart';
import 'features/support/presentation/cubit/support_cubit.dart';
import 'features/rating/data/rating_repository.dart';
import 'features/rating/presentation/cubit/rating_cubit.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final storage = TokenStorage();
  final sessionEvents = AuthSessionEvents();
  final themeProvider = await ThemeProvider.load();
  DioClient.init(storage, sessionEvents);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: themeProvider),
        BlocProvider(
          create: (_) => AuthCubit(
            AuthRepository(AuthApi(), storage, storage, sessionEvents),
          )..restoreSession(),
        ),
        BlocProvider(
          create: (context) => AccountCubit(
            AccountRepository(AccountApi(), storage, storage),
            context.read<AuthCubit>(),
          ),
        ),
        BlocProvider(
          create: (_) => InventoryCubit(InventoryRepository(InventoryApi())),
        ),
        BlocProvider(
          create: (_) => SuppliersCubit(SuppliersRepository(SuppliersApi())),
        ),
        BlocProvider(create: (_) => OrdersCubit(OrdersRepository(OrdersApi()))),
        // App-wide, not per screen: the badge on the buying screens, the cart
        // page and the restock suggestions all read the same count, and a
        // second copy would immediately start disagreeing with the first.
        BlocProvider(
          create: (_) =>
              PurchaseCartCubit(PurchaseCartRepository(PurchaseCartApi())),
        ),
        BlocProvider(create: (_) => SalesCubit(SalesRepository(SalesApi()))),
        BlocProvider(
          create: (_) => ReportsCubit(ReportsRepository(ReportsApi())),
        ),
        BlocProvider(
          create: (_) => EmployeesCubit(EmployeesRepository(EmployeesApi())),
        ),
        BlocProvider(
          create: (_) => EmployeeWorkspaceCubit(
            EmployeeWorkspaceRepository(EmployeeWorkspaceApi()),
          ),
        ),
        BlocProvider(create: (_) => TasksCubit(TasksRepository(TasksApi()))),
        BlocProvider(
          create: (_) =>
              NotificationsCubit(NotificationsRepository(NotificationsApi())),
        ),
        BlocProvider(
          create: (context) => EmployeeOffersCubit(
            EmployeeOffersRepository(EmployeeOffersApi()),
            context.read<AuthCubit>().reloadSession,
          ),
        ),
        BlocProvider(
          create: (_) => SupportCubit(SupportRepository(SupportApi())),
        ),
        BlocProvider(create: (_) => RatingCubit(RatingRepository(RatingApi()))),
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
              (current is AuthAuthenticated &&
                  previous is! AuthAuthenticated) ||
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
            themeMode: themeProvider.themeMode,
            theme: AppTheme.light,
            darkTheme: AppTheme.dark,
            home: const AppStartupGate(),
          ),
        );
      },
    );
  }
}
