import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/widgets/custom_button.dart';
import 'package:phamacy_managment/features/account/presentation/cubit/account_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/settings_change_password_page.dart';

import 'account_cubit_test.dart';

// The AppBar title and the submit button both render the literal text
// "Change Password", so find.text(...) is ambiguous. Target the button
// widget specifically instead.
final _submitButton = find.byType(CustomButton);

void main() {
  Widget host(AccountCubit cubit) {
    return MaterialApp(
      home: BlocProvider<AccountCubit>.value(
        value: cubit,
        child: const SettingsChangePasswordPage(),
      ),
    );
  }

  testWidgets('valid input submits and calls changePassword once', (
    tester,
  ) async {
    final api = FakeAccountCubitApi();
    final harness = Harness(accountApi: api);
    await tester.pumpWidget(host(harness.account));

    await tester.enterText(
      find.byKey(const ValueKey('current-password-field')),
      'password',
    );
    await tester.enterText(
      find.byKey(const ValueKey('new-password-field')),
      'new-password',
    );
    await tester.enterText(
      find.byKey(const ValueKey('confirm-password-field')),
      'new-password',
    );
    await tester.tap(_submitButton);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 350));

    expect(api.passwordCalls, 1);
    await closeWidgetHarness(tester, harness);
  });

  testWidgets('wrong current password surfaces inline field error', (
    tester,
  ) async {
    final harness = Harness(
      accountApi: FakeAccountCubitApi()..wrongPassword = true,
    );
    await tester.pumpWidget(host(harness.account));

    await tester.enterText(
      find.byKey(const ValueKey('current-password-field')),
      'wrong',
    );
    await tester.enterText(
      find.byKey(const ValueKey('new-password-field')),
      'new-password',
    );
    await tester.enterText(
      find.byKey(const ValueKey('confirm-password-field')),
      'new-password',
    );
    await tester.tap(_submitButton);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 350));

    expect(find.text('The current password is incorrect.'), findsOneWidget);
    await closeWidgetHarness(tester, harness);
  });

  testWidgets(
    'mismatched confirmation blocks submission without calling the API',
    (tester) async {
      final api = FakeAccountCubitApi();
      final harness = Harness(accountApi: api);
      await tester.pumpWidget(host(harness.account));

      await tester.enterText(
        find.byKey(const ValueKey('current-password-field')),
        'password',
      );
      await tester.enterText(
        find.byKey(const ValueKey('new-password-field')),
        'new-password',
      );
      await tester.enterText(
        find.byKey(const ValueKey('confirm-password-field')),
        'different',
      );
      await tester.tap(_submitButton);
      await tester.pump();

      expect(api.passwordCalls, 0);
      await closeWidgetHarness(tester, harness);
    },
  );
}
