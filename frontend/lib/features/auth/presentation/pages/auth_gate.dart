import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import 'account_type_page.dart';
import 'active_pharmacy_selection_page.dart';
import 'employee_navigation_page.dart';
import 'main_navigation_page.dart';
import 'session_status_page.dart';

class AuthGate extends StatelessWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<AuthCubit, AuthState>(
      builder: (context, state) {
        if (state is AuthRestoring || state is AuthLoading) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }

        if (state is AuthAuthenticated) {
          return state.session.actor.type.name == 'employee'
              ? EmployeeNavigationPage(session: state.session)
              : const MainNavigationPage();
        }

        if (state is AuthPharmacySelectionRequired) {
          return ActivePharmacySelectionPage(
            session: state.session,
            errorMessage: state.errorMessage,
          );
        }

        if (state is AuthAccessRestricted) {
          return SessionStatusPage(session: state.session);
        }

        if (state is AuthRestoreFailure) {
          return Scaffold(
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(state.message, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: () =>
                          context.read<AuthCubit>().restoreSession(),
                      child: const Text('Retry'),
                    ),
                    TextButton(
                      onPressed: () => context.read<AuthCubit>().logout(),
                      child: const Text('Logout'),
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        return const AccountTypePage();
      },
    );
  }
}
