class PharmacyLocationDraft {
  final double latitude;
  final double longitude;
  final String? suggestedAddress;

  const PharmacyLocationDraft({
    required this.latitude,
    required this.longitude,
    this.suggestedAddress,
  });

  PharmacyLocationDraft copyWith({
    double? latitude,
    double? longitude,
    String? suggestedAddress,
  }) {
    return PharmacyLocationDraft(
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      suggestedAddress: suggestedAddress,
    );
  }
}
