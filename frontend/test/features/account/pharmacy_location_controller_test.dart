import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/account/data/location_services.dart';
import 'package:phamacy_managment/features/account/domain/pharmacy_location_draft.dart';
import 'package:phamacy_managment/features/account/domain/pharmacy_edit_draft.dart';
import 'package:phamacy_managment/features/account/presentation/controllers/pharmacy_location_controller.dart';

void main() {
  test('existing location initializes draft and tap and drag replace it', () {
    final controller = _controller(
      initial: const PharmacyLocationDraft(latitude: 1, longitude: 2),
    );

    expect(controller.draft?.latitude, 1);
    controller.select(3, 4);
    expect(controller.draft?.latitude, 3);
    expect(controller.draft?.longitude, 4);
    controller.markerDragged(5, 6);
    expect(controller.draft?.latitude, 5);
    expect(controller.draft?.longitude, 6);
  });

  test(
    'current location is requested only after the explicit action',
    () async {
      final location = FakeCurrentLocationService(
        const CurrentLocationResult.success(
          PharmacyLocationDraft(latitude: 33.5, longitude: 36.2),
        ),
      );
      final controller = PharmacyLocationController(
        locationService: location,
        addressLookup: FakeAddressLookup(),
      );

      expect(location.calls, 0);
      final selected = await controller.useCurrentLocation();

      expect(location.calls, 1);
      expect(selected?.latitude, 33.5);
      expect(controller.draft?.longitude, 36.2);
    },
  );

  for (final failure in LocationFailure.values) {
    test(
      '$failure is non-blocking and manual selection remains available',
      () async {
        final controller = PharmacyLocationController(
          locationService: FakeCurrentLocationService(
            CurrentLocationResult.failure(failure),
          ),
          addressLookup: FakeAddressLookup(),
        );

        expect(await controller.useCurrentLocation(), isNull);
        expect(controller.failure, failure);
        controller.select(10, 20);
        expect(controller.canConfirm, isTrue);
        expect(controller.failure, isNull);
      },
    );
  }

  test(
    'confirm returns a draft only and performs best-effort reverse geocoding',
    () async {
      final lookup = FakeAddressLookup(address: 'Suggested address');
      final controller = _controller(addressLookup: lookup)..select(10, 20);

      final confirmed = await controller.confirm();

      expect(lookup.calls, 1);
      expect(confirmed?.latitude, 10);
      expect(confirmed?.suggestedAddress, 'Suggested address');
    },
  );

  test('reverse geocoding failure keeps selected coordinates usable', () async {
    final controller = _controller(addressLookup: FakeAddressLookup())
      ..select(10, 20);

    final confirmed = await controller.confirm();

    expect(confirmed?.latitude, 10);
    expect(confirmed?.longitude, 20);
    expect(confirmed?.suggestedAddress, isNull);
  });

  test(
    'suggested address never overwrites manual address without explicit use',
    () {
      const manual = PharmacyEditDraft(address: 'Manual address');
      final located = manual.applyLocation(
        const PharmacyLocationDraft(
          latitude: 10,
          longitude: 20,
          suggestedAddress: 'Suggested address',
        ),
      );

      expect(located.address, 'Manual address');
      expect(located.suggestedAddress, 'Suggested address');
      expect(located.useSuggestedAddress().address, 'Suggested address');
    },
  );
}

PharmacyLocationController _controller({
  PharmacyLocationDraft? initial,
  FakeAddressLookup? addressLookup,
}) {
  return PharmacyLocationController(
    locationService: FakeCurrentLocationService(
      const CurrentLocationResult.failure(LocationFailure.permissionDenied),
    ),
    addressLookup: addressLookup ?? FakeAddressLookup(),
    initial: initial,
  );
}

class FakeCurrentLocationService implements CurrentLocationService {
  final CurrentLocationResult result;
  int calls = 0;

  FakeCurrentLocationService(this.result);

  @override
  Future<CurrentLocationResult> getCurrentLocation() async {
    calls++;
    return result;
  }

  @override
  Future<bool> openAppSettings() async => true;

  @override
  Future<bool> openLocationSettings() async => true;
}

class FakeAddressLookup implements AddressLookupService {
  final String? address;
  int calls = 0;

  FakeAddressLookup({this.address});

  @override
  Future<String?> addressFor(double latitude, double longitude) async {
    calls++;
    return address;
  }
}
