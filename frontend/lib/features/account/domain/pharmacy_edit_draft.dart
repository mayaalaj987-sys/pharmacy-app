import 'pharmacy_location_draft.dart';

class PharmacyEditDraft {
  final String address;
  final double? latitude;
  final double? longitude;
  final String? suggestedAddress;
  final bool includeCoordinates;

  const PharmacyEditDraft({
    required this.address,
    this.latitude,
    this.longitude,
    this.suggestedAddress,
    this.includeCoordinates = false,
  });

  PharmacyEditDraft applyLocation(PharmacyLocationDraft location) {
    return PharmacyEditDraft(
      address: address,
      latitude: location.latitude,
      longitude: location.longitude,
      suggestedAddress: location.suggestedAddress,
      includeCoordinates: true,
    );
  }

  PharmacyEditDraft clearLocation() {
    return PharmacyEditDraft(address: address, includeCoordinates: true);
  }

  PharmacyEditDraft useSuggestedAddress() {
    final suggestion = suggestedAddress?.trim();
    return PharmacyEditDraft(
      address: suggestion == null || suggestion.isEmpty ? address : suggestion,
      latitude: latitude,
      longitude: longitude,
      suggestedAddress: suggestedAddress,
      includeCoordinates: includeCoordinates,
    );
  }
}
