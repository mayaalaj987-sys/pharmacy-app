import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/account/data/location_services.dart';
import 'package:phamacy_managment/features/account/domain/pharmacy_location_draft.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/pharmacy_location_picker_page.dart';
import 'package:phamacy_managment/features/auth/presentation/widgets/pharmacy_location_field.dart';

import '../../account/pharmacy_location_controller_test.dart';

/// The address field shared by signup and Add Pharmacy.
///
/// The map is deliberately optional here, so the tests that matter most are
/// the ones proving registration is still possible without ever touching it.
void main() {
  late TextEditingController address;

  setUp(() => address = TextEditingController());
  tearDown(() => address.dispose());

  Future<void> pumpField(
    WidgetTester tester, {
    double? latitude,
    double? longitude,
    String? suggestedAddress,
    bool enabled = true,
    ValueChanged<PharmacyLocationDraft>? onPicked,
    VoidCallback? onCleared,
    VoidCallback? onAccepted,
    CurrentLocationService? locationService,
    AddressLookupService? addressLookup,
  }) {
    return tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PharmacyLocationField(
            addressController: address,
            latitude: latitude,
            longitude: longitude,
            suggestedAddress: suggestedAddress,
            enabled: enabled,
            onLocationPicked: onPicked ?? (_) {},
            onLocationCleared: onCleared ?? () {},
            onSuggestionAccepted: onAccepted ?? () {},
            locationService: locationService,
            addressLookup: addressLookup,
          ),
        ),
      ),
    );
  }

  testWidgets('the map is offered, not required', (tester) async {
    await pumpField(tester);

    // Typing the address has to stay a complete answer on its own.
    expect(find.text('No map location — optional'), findsOneWidget);
    expect(find.text('Pick on Map'), findsOneWidget);
    expect(find.byType(TextField), findsOneWidget);
  });

  testWidgets('a picked point is shown and can be changed', (tester) async {
    await pumpField(tester, latitude: 33.5138, longitude: 36.2765);

    expect(find.text('33.513800, 36.276500'), findsOneWidget);
    expect(find.text('Change on Map'), findsOneWidget);
    expect(find.text('Clear'), findsOneWidget);
  });

  testWidgets('clearing is only offered once a point exists', (tester) async {
    await pumpField(tester);
    expect(find.text('Clear'), findsNothing);

    var cleared = false;
    await pumpField(
      tester,
      latitude: 1,
      longitude: 2,
      onCleared: () => cleared = true,
    );
    await tester.tap(find.byKey(const ValueKey('clear-pharmacy-location-button')));

    expect(cleared, isTrue);
  });

  testWidgets('the geocoded address is a suggestion, not an overwrite', (
    tester,
  ) async {
    address.text = 'Beside Al-Shifa Hospital';
    var accepted = false;
    await pumpField(
      tester,
      latitude: 33.5138,
      longitude: 36.2765,
      suggestedAddress: 'Al-Mazzeh Street, Damascus',
      onAccepted: () => accepted = true,
    );

    // What the owner typed survives until they ask for the geocoder's wording.
    expect(address.text, 'Beside Al-Shifa Hospital');
    expect(find.text('Al-Mazzeh Street, Damascus'), findsOneWidget);

    await tester.tap(
      find.byKey(const ValueKey('use-suggested-pharmacy-address-button')),
    );
    expect(accepted, isTrue);
  });

  testWidgets('no suggestion card appears without a suggestion', (
    tester,
  ) async {
    await pumpField(tester, latitude: 1, longitude: 2);

    expect(find.text('Suggested address'), findsNothing);
  });

  testWidgets('everything is locked while the form is submitting', (
    tester,
  ) async {
    await pumpField(
      tester,
      latitude: 1,
      longitude: 2,
      suggestedAddress: 'Somewhere',
      enabled: false,
    );

    for (final key in const [
      'pick-pharmacy-location-button',
      'clear-pharmacy-location-button',
      'use-suggested-pharmacy-address-button',
    ]) {
      final button = tester.widget<ButtonStyleButton>(
        find.byKey(ValueKey(key)),
      );
      expect(button.onPressed, isNull, reason: key);
    }

    final field = tester.widget<TextField>(find.byType(TextField));
    expect(field.enabled, isFalse);
  });

  testWidgets('picking on the map returns a point and a suggested address', (
    tester,
  ) async {
    PharmacyLocationDraft? picked;
    await pumpField(
      tester,
      onPicked: (draft) => picked = draft,
      locationService: FakeCurrentLocationService(
        const CurrentLocationResult.success(
          PharmacyLocationDraft(latitude: 33.5138, longitude: 36.2765),
        ),
      ),
      addressLookup: FakeAddressLookup(address: 'Al-Mazzeh Street, Damascus'),
    );

    await tester.tap(
      find.byKey(const ValueKey('pick-pharmacy-location-button')),
    );
    await tester.pumpAndSettle();
    expect(find.byType(PharmacyLocationPickerPage), findsOneWidget);

    await tester.tap(
      find.byKey(const ValueKey('use-current-location-button')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('confirm-location-button')));
    await tester.pumpAndSettle();

    expect(picked?.latitude, 33.5138);
    expect(picked?.longitude, 36.2765);
    expect(picked?.suggestedAddress, 'Al-Mazzeh Street, Damascus');
  });

  testWidgets('cancelling the map leaves the field untouched', (tester) async {
    var picked = false;
    await pumpField(
      tester,
      onPicked: (_) => picked = true,
      locationService: FakeCurrentLocationService(
        const CurrentLocationResult.failure(LocationFailure.permissionDenied),
      ),
      addressLookup: FakeAddressLookup(),
    );

    await tester.tap(
      find.byKey(const ValueKey('pick-pharmacy-location-button')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('map-cancel-button')));
    await tester.pumpAndSettle();

    expect(picked, isFalse);
    expect(find.text('No map location — optional'), findsOneWidget);
  });
}
