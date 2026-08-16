import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/widgets/settings_page/settings_deactivate_account_tile.dart';
import 'package:phamacy_managment/core/widgets/settings_page/settings_logout_tile.dart';
import 'package:phamacy_managment/core/widgets/settings_page/settings_profile_card.dart';
import 'package:phamacy_managment/features/account/presentation/widgets/profile_avatar.dart';
import 'package:phamacy_managment/features/auth/data/models/auth_session_model.dart';

void main() {
  testWidgets('profile avatar has visible null and broken URL fallbacks', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              ProfileAvatar(imageUrl: null, name: 'Owner'),
              ProfileAvatar(imageUrl: 'not a valid URL', name: 'Broken'),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const ValueKey('profile-image-fallback')),
      findsNWidgets(2),
    );
    expect(find.text('O'), findsOneWidget);
    expect(find.text('B'), findsOneWidget);
  });

  testWidgets('settings profile card renders backend session values', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: SettingsProfileCard(sessionOverride: _session())),
      ),
    );

    expect(find.text('Owner'), findsOneWidget);
    expect(find.text('owner@example.test'), findsOneWidget);
    expect(find.text('0999000000'), findsOneWidget);
    expect(find.text('Pharmacy'), findsOneWidget);
  });

  testWidgets('logout cancel makes no request and confirm is single-flight', (
    tester,
  ) async {
    var calls = 0;
    final completer = Completer<void>();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SettingsLogoutTile(
            onLogout: () async {
              calls++;
              await completer.future;
            },
          ),
        ),
      ),
    );

    await tester.tap(find.text('Logout').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(calls, 0);

    await tester.tap(find.text('Logout').first);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Logout'));
    await tester.pump();
    expect(calls, 1);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.byKey(const ValueKey('logout-confirm-button')),
          )
          .onPressed,
      isNull,
    );

    completer.complete();
    await tester.pumpAndSettle();
    expect(calls, 1);
  });

  testWidgets('deactivation warns, requires password, and is single-flight', (
    tester,
  ) async {
    var calls = 0;
    final completer = Completer<bool>();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SettingsDeactivateAccountTile(
            onDeactivate: (password) async {
              calls++;
              expect(password, 'password');
              return completer.future;
            },
          ),
        ),
      ),
    );

    await tester.tap(find.text('Deactivate Account'));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('stock, sales, and financial history'),
      findsOneWidget,
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Deactivate'));
    await tester.pump();
    expect(calls, 0);

    await tester.enterText(
      find.byKey(const ValueKey('deactivation-password-field')),
      'password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Deactivate'));
    await tester.pump();
    expect(calls, 1);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.byKey(const ValueKey('deactivate-confirm-button')),
          )
          .onPressed,
      isNull,
    );

    completer.complete(true);
    await tester.pumpAndSettle();
    expect(calls, 1);
  });
}

AuthSession _session() {
  final pharmacy = {
    'id': 7,
    'name': 'Pharmacy',
    'address': 'Address',
    'status': 'approved',
    'latitude': 33.5,
    'longitude': 36.2,
  };
  return AuthSession.fromJson({
    'actor': {
      'id': 1,
      'type': 'pharmacist',
      'role': 'owner',
      'status': null,
      'name': 'Owner',
      'email': 'owner@example.test',
      'phone': '0999000000',
      'profile_image_url': null,
    },
    'available_pharmacies': [pharmacy],
    'active_pharmacy': pharmacy,
    'access': {
      'operational': true,
      'code': 'ready',
      'requires_active_pharmacy': false,
    },
  });
}
