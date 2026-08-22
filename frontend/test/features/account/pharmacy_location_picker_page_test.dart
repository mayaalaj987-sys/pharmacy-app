import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:phamacy_managment/features/account/data/location_services.dart';
import 'package:phamacy_managment/features/account/domain/pharmacy_location_draft.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/pharmacy_location_picker_page.dart';

import 'pharmacy_location_controller_test.dart';

void main() {
  testWidgets('initial state shows placeholder and disabled confirm', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: PharmacyLocationPickerPage(
          locationService: FakeCurrentLocationService(
            const CurrentLocationResult.success(
              PharmacyLocationDraft(latitude: 1, longitude: 2),
            ),
          ),
          addressLookup: FakeAddressLookup(),
        ),
      ),
    );

    expect(find.text('Tap the map to place a marker.'), findsOneWidget);
    final confirm = tester.widget<FilledButton>(
      find.byKey(const ValueKey('confirm-location-button')),
    );
    expect(confirm.onPressed, isNull);
  });

  testWidgets(
    'using current location shows coordinates and confirm pops with the draft',
    (tester) async {
      PharmacyLocationDraft? popped;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: ElevatedButton(
                onPressed: () async {
                  popped = await Navigator.push<PharmacyLocationDraft>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => PharmacyLocationPickerPage(
                        locationService: FakeCurrentLocationService(
                          const CurrentLocationResult.success(
                            PharmacyLocationDraft(
                              latitude: 33.5,
                              longitude: 36.2,
                            ),
                          ),
                        ),
                        addressLookup: FakeAddressLookup(
                          address: 'Suggested St',
                        ),
                      ),
                    ),
                  );
                },
                child: const Text('open'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();

      await tester.tap(
        find.byKey(const ValueKey('use-current-location-button')),
      );
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey('map-selected-coordinates')),
        findsOneWidget,
      );
      expect(find.text('33.500000, 36.200000'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('confirm-location-button')));
      await tester.pumpAndSettle();

      expect(popped?.latitude, 33.5);
      expect(popped?.longitude, 36.2);
      expect(popped?.suggestedAddress, 'Suggested St');
    },
  );

  testWidgets('location failure shows a non-blocking message', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: PharmacyLocationPickerPage(
          locationService: FakeCurrentLocationService(
            const CurrentLocationResult.failure(
              LocationFailure.permissionDenied,
            ),
          ),
          addressLookup: FakeAddressLookup(),
        ),
      ),
    );

    await tester.tap(find.byKey(const ValueKey('use-current-location-button')));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const ValueKey('location-failure-message')),
      findsOneWidget,
    );
    final confirm = tester.widget<FilledButton>(
      find.byKey(const ValueKey('confirm-location-button')),
    );
    expect(confirm.onPressed, isNull);
  });

  testWidgets('an unplaced pharmacy opens the camera over Syria', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: PharmacyLocationPickerPage(
          locationService: FakeCurrentLocationService(
            const CurrentLocationResult.failure(LocationFailure.unavailable),
          ),
          addressLookup: FakeAddressLookup(),
        ),
      ),
    );

    // At world zoom the country is a few pixels wide and every owner has to
    // pan across an ocean before they can place anything.
    final map = tester.widget<FlutterMap>(
      find.byKey(const ValueKey('pharmacy-map')),
    );
    expect(map.options.initialCenter.latitude, closeTo(34.8, 0.5));
    expect(map.options.initialCenter.longitude, closeTo(38.0, 0.5));
    expect(map.options.initialZoom, greaterThanOrEqualTo(5));
  });

  testWidgets('an already placed pharmacy opens on its own street', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: PharmacyLocationPickerPage(
          initialLocation: const PharmacyLocationDraft(
            latitude: 33.5138,
            longitude: 36.2765,
          ),
          locationService: FakeCurrentLocationService(
            const CurrentLocationResult.failure(LocationFailure.unavailable),
          ),
          addressLookup: FakeAddressLookup(),
        ),
      ),
    );

    final map = tester.widget<FlutterMap>(
      find.byKey(const ValueKey('pharmacy-map')),
    );
    expect(map.options.initialCenter.latitude, 33.5138);
    expect(map.options.initialZoom, greaterThanOrEqualTo(15));
  });
}
