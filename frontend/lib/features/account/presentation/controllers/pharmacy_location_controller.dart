import 'package:flutter/foundation.dart';

import '../../data/location_services.dart';
import '../../domain/pharmacy_location_draft.dart';

class PharmacyLocationController extends ChangeNotifier {
  final CurrentLocationService locationService;
  final AddressLookupService addressLookup;

  PharmacyLocationDraft? _draft;
  bool _locating = false;
  bool _confirming = false;
  LocationFailure? _failure;

  PharmacyLocationController({
    required this.locationService,
    required this.addressLookup,
    PharmacyLocationDraft? initial,
  }) : _draft = initial;

  PharmacyLocationDraft? get draft => _draft;
  bool get locating => _locating;
  bool get confirming => _confirming;
  LocationFailure? get failure => _failure;
  bool get canConfirm => _draft != null && !_confirming;

  void select(double latitude, double longitude) {
    _draft = PharmacyLocationDraft(latitude: latitude, longitude: longitude);
    _failure = null;
    notifyListeners();
  }

  void markerDragged(double latitude, double longitude) {
    select(latitude, longitude);
  }

  Future<PharmacyLocationDraft?> useCurrentLocation() async {
    if (_locating) return null;
    _locating = true;
    _failure = null;
    notifyListeners();
    final result = await locationService.getCurrentLocation();
    _locating = false;
    if (result.location != null) {
      _draft = result.location;
    } else {
      _failure = result.failure;
    }
    notifyListeners();
    return result.location;
  }

  Future<PharmacyLocationDraft?> confirm() async {
    final selected = _draft;
    if (selected == null || _confirming) return null;
    _confirming = true;
    notifyListeners();
    final suggested = await addressLookup.addressFor(
      selected.latitude,
      selected.longitude,
    );
    _confirming = false;
    _draft = selected.copyWith(suggestedAddress: suggested);
    notifyListeners();
    return _draft;
  }
}
