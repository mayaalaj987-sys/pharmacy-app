import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/theme/app_theme.dart';
import 'package:phamacy_managment/features/onboarding/presentation/onboarding_page.dart';

void main() {
  Future<void> pumpAtSize(WidgetTester tester, Size size) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = size;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        home: OnboardingPage(onFinished: () async {}),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('onboarding fits a compact phone without overflow', (
    tester,
  ) async {
    await pumpAtSize(tester, const Size(320, 568));

    expect(find.text('Your pharmacy, clearly organized'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('onboarding stays centered on a wide screen', (tester) async {
    await pumpAtSize(tester, const Size(1024, 1366));

    final pageSize = tester.getSize(find.byType(OnboardingPage));
    expect(pageSize.width, 1024);
    expect(find.text('Next'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
