import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/account/presentation/cubit/account_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/settings_edit_pharmacy_page.dart';

import 'account_cubit_test.dart';

void main() {
  Widget host(Harness harness) {
    return MaterialApp(
      home: MultiBlocProvider(
        providers: [
          BlocProvider<AuthCubit>.value(value: harness.auth),
          BlocProvider<AccountCubit>.value(value: harness.account),
        ],
        child: const SettingsEditPharmacyPage(),
      ),
    );
  }

  testWidgets('renders existing pharmacy name, address, and coordinates', (
    tester,
  ) async {
    final harness = Harness();
    await tester.pumpWidget(host(harness));

    expect(find.text('Pharmacy'), findsOneWidget);
    expect(find.text('Address'), findsOneWidget);
    expect(find.text('33.500000, 36.200000'), findsOneWidget);
    await closeWidgetHarness(tester, harness);
  });

  testWidgets('empty name blocks save without calling the API', (tester) async {
    final api = FakeAccountCubitApi();
    final harness = Harness(accountApi: api);
    await tester.pumpWidget(host(harness));

    await tester.enterText(
      find.byKey(const ValueKey('pharmacy-name-field')),
      '',
    );
    await tester.tap(find.text('Save Changes'));
    await tester.pump();

    expect(
      find.text('Pharmacy name and address are required.'),
      findsOneWidget,
    );
    await closeWidgetHarness(tester, harness);
  });

  testWidgets('valid save calls updatePharmacy with the new name', (
    tester,
  ) async {
    final api = FakeAccountCubitApi();
    final harness = Harness(accountApi: api);
    await tester.pumpWidget(host(harness));

    await tester.enterText(
      find.byKey(const ValueKey('pharmacy-name-field')),
      'New Pharmacy Name',
    );
    await tester.tap(find.text('Save Changes'));
    await tester.pumpAndSettle();

    expect(harness.auth.session?.activePharmacy?.name, 'New Pharmacy Name');
    await closeWidgetHarness(tester, harness);
  });
}
