/// A pharmacy's invitation to cover one shift.
class JobOffer {
  final int id;
  final String status;
  final String shift;
  final double? salary;
  final DateTime? offeredAt;

  /// Whether it can be acted on right now. Derived server-side from four things
  /// at once — the offer is still pending, the holder is unemployed, the
  /// pharmacy is operational, and nobody has taken that shift — so an offer can
  /// become acceptable again on its own the day a job ends.
  final bool acceptable;

  /// Why not, when [acceptable] is false.
  final String? unavailableReason;

  final OfferPharmacy? pharmacy;
  final OfferOwner? owner;

  const JobOffer({
    required this.id,
    required this.status,
    required this.shift,
    required this.acceptable,
    this.salary,
    this.offeredAt,
    this.unavailableReason,
    this.pharmacy,
    this.owner,
  });

  String get shiftLabel => shift == 'morning' ? 'Morning' : 'Evening';

  /// Plain-English reason the button is inert, written for the applicant rather
  /// than for a log.
  String? get unavailableExplanation => switch (unavailableReason) {
    'already_employed' =>
      'You are currently employed. Leave your job to accept this.',
    'offer_withdrawn' => 'This pharmacy withdrew the offer.',
    'pharmacy_unavailable' => 'This pharmacy is not operating right now.',
    'shift_taken' => 'Someone else now covers this shift.',
    'offer_not_pending' => 'This offer is no longer open.',
    _ => null,
  };

  factory JobOffer.fromJson(Map<String, dynamic> json) {
    final rawSalary = json['salary'];
    final rawOffered = json['offered_at']?.toString();

    return JobOffer(
      id: json['id'] is num ? (json['id'] as num).toInt() : 0,
      status: json['status']?.toString() ?? '',
      shift: json['shift']?.toString() ?? '',
      acceptable: json['acceptable'] == true,
      unavailableReason: json['unavailable_reason']?.toString(),
      salary: rawSalary is num
          ? rawSalary.toDouble()
          : double.tryParse(rawSalary?.toString() ?? ''),
      offeredAt: rawOffered == null || rawOffered.isEmpty
          ? null
          : DateTime.tryParse(rawOffered),
      pharmacy: json['pharmacy'] is Map<String, dynamic>
          ? OfferPharmacy.fromJson(json['pharmacy'] as Map<String, dynamic>)
          : null,
      owner: json['owner'] is Map<String, dynamic>
          ? OfferOwner.fromJson(json['owner'] as Map<String, dynamic>)
          : null,
    );
  }
}

class OfferPharmacy {
  final int id;
  final String name;
  final String address;
  final double? latitude;
  final double? longitude;

  const OfferPharmacy({
    required this.id,
    required this.name,
    required this.address,
    this.latitude,
    this.longitude,
  });

  bool get hasLocation => latitude != null && longitude != null;

  factory OfferPharmacy.fromJson(Map<String, dynamic> json) {
    double? coordinate(dynamic value) => value is num
        ? value.toDouble()
        : double.tryParse(value?.toString() ?? '');

    return OfferPharmacy(
      id: json['id'] is num ? (json['id'] as num).toInt() : 0,
      name: json['name']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      latitude: coordinate(json['latitude']),
      longitude: coordinate(json['longitude']),
    );
  }
}

class OfferOwner {
  final String name;

  /// The phone is nullable on the backend and the email always exists, so the
  /// UI shows whichever is there rather than guessing.
  final String? phone;
  final String? email;

  const OfferOwner({required this.name, this.phone, this.email});

  String? get contact {
    final number = phone?.trim();
    if (number != null && number.isNotEmpty) return number;
    final address = email?.trim();
    return address == null || address.isEmpty ? null : address;
  }

  factory OfferOwner.fromJson(Map<String, dynamic> json) => OfferOwner(
    name: json['name']?.toString() ?? '',
    phone: json['phone']?.toString(),
    email: json['email']?.toString(),
  );
}

/// Where this person works, when they do.
class OfferEmployment {
  final int pharmacyId;
  final String pharmacyName;
  final String? shift;

  const OfferEmployment({
    required this.pharmacyId,
    required this.pharmacyName,
    this.shift,
  });

  factory OfferEmployment.fromJson(Map<String, dynamic> json) =>
      OfferEmployment(
        pharmacyId: json['pharmacy_id'] is num
            ? (json['pharmacy_id'] as num).toInt()
            : 0,
        pharmacyName: json['pharmacy_name']?.toString() ?? '',
        shift: json['shift']?.toString(),
      );
}

/// The whole inbox as one value, so the cubit holds a single thing.
class JobOfferInbox {
  final List<JobOffer> offers;

  /// How many can be accepted now, which is not the same as how many are
  /// pending: every offer is inert while its holder already has a job.
  final int actionable;

  final OfferEmployment? employment;

  const JobOfferInbox({
    this.offers = const <JobOffer>[],
    this.actionable = 0,
    this.employment,
  });

  factory JobOfferInbox.fromJson(Map<String, dynamic> json) {
    final raw = json['offers'];
    final counts = json['counts'];

    return JobOfferInbox(
      offers: raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(JobOffer.fromJson)
                .toList(growable: false)
          : const <JobOffer>[],
      actionable: counts is Map<String, dynamic> && counts['actionable'] is num
          ? (counts['actionable'] as num).toInt()
          : 0,
      employment: json['employment'] is Map<String, dynamic>
          ? OfferEmployment.fromJson(json['employment'] as Map<String, dynamic>)
          : null,
    );
  }
}
