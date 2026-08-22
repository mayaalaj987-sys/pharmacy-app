import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/account/presentation/cubit/account_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/settings_edit_profile_page.dart';

import 'account_cubit_test.dart';

void main() {
  Widget host(Harness harness) {
    return MaterialApp(
      home: MultiBlocProvider(
        providers: [
          BlocProvider<AuthCubit>.value(value: harness.auth),
          BlocProvider<AccountCubit>.value(value: harness.account),
        ],
        child: const SettingsEditProfilePage(),
      ),
    );
  }

  testWidgets('renders existing name and a read-only email field', (
    tester,
  ) async {
    final harness = Harness();
    await tester.pumpWidget(host(harness));

    expect(find.text('Owner'), findsOneWidget);
    final email = tester.widget<TextField>(
      find.byKey(const ValueKey('profile-email-field')),
    );
    expect(email.readOnly, isTrue);
    await closeWidgetHarness(tester, harness);
  });

  testWidgets('empty name blocks save without calling the API', (tester) async {
    final api = FakeAccountCubitApi();
    final harness = Harness(accountApi: api);
    await tester.pumpWidget(host(harness));

    await tester.enterText(
      find.byKey(const ValueKey('profile-name-field')),
      '   ',
    );
    await tester.tap(find.text('Save Changes'));
    await tester.pump();

    expect(find.text('Name is required.'), findsOneWidget);
    await closeWidgetHarness(tester, harness);
  });

  testWidgets('valid save calls updateProfile with the trimmed name', (
    tester,
  ) async {
    final api = FakeAccountCubitApi();
    final harness = Harness(accountApi: api);
    await tester.pumpWidget(host(harness));

    await tester.enterText(
      find.byKey(const ValueKey('profile-name-field')),
      'Updated Owner',
    );
    await tester.tap(find.text('Save Changes'));
    await tester.pumpAndSettle();

    expect(harness.auth.session?.actor.name, 'Updated Owner');
    await closeWidgetHarness(tester, harness);
  });
}
