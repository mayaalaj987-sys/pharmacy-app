import 'dart:async';

import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';

import '../domain/pharmacy_location_draft.dart';

enum LocationFailure {
  servicesDisabled,
  permissionDenied,
  permissionDeniedForever,
  timeout,
  unavailable,
}

class CurrentLocationResult {
  final PharmacyLocationDraft? location;
  final LocationFailure? failure;

  const CurrentLocationResult.success(this.location) : failure = null;

  const CurrentLocationResult.failure(this.failure) : location = null;
}

abstract class CurrentLocationService {
  Future<CurrentLocationResult> getCurrentLocation();

  Future<bool> openAppSettings();

  Future<bool> openLocationSettings();
}

class GeolocatorCurrentLocationService implements CurrentLocationService {
  @override
  Future<CurrentLocationResult> getCurrentLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return const CurrentLocationResult.failure(
          LocationFailure.servicesDisabled,
        );
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.deniedForever) {
        return const CurrentLocationResult.failure(
          LocationFailure.permissionDeniedForever,
        );
      }
      if (permission == LocationPermission.denied) {
        return const CurrentLocationResult.failure(
          LocationFailure.permissionDenied,
        );
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 12),
        ),
      );
      return CurrentLocationResult.success(
        PharmacyLocationDraft(
          latitude: position.latitude,
          longitude: position.longitude,
        ),
      );
    } on TimeoutException {
      return const CurrentLocationResult.failure(LocationFailure.timeout);
    } catch (_) {
      return const CurrentLocationResult.failure(LocationFailure.unavailable);
    }
  }

  @override
  Future<bool> openAppSettings() => Geolocator.openAppSettings();

  @override
  Future<bool> openLocationSettings() => Geolocator.openLocationSettings();
}

abstract class AddressLookupService {
  Future<String?> addressFor(double latitude, double longitude);
}

class GeocodingAddressLookupService implements AddressLookupService {
  final Geocoding _geocoding;

  GeocodingAddressLookupService({Geocoding? geocoding})
    : _geocoding = geocoding ?? Geocoding();

  @override
  Future<String?> addressFor(double latitude, double longitude) async {
    try {
      final places = await _geocoding.placemarkFromCoordinates(
        latitude,
        longitude,
      );
      if (places.isEmpty) return null;
      final place = places.first;
      final parts =
          <String?>[
                place.street,
                place.locality,
                place.administrativeArea,
                place.country,
              ]
              .whereType<String>()
              .map((part) => part.trim())
              .where((part) => part.isNotEmpty);
      final address = parts.toSet().join(', ');
      return address.isEmpty ? null : address;
    } catch (_) {
      return null;
    }
  }
}
