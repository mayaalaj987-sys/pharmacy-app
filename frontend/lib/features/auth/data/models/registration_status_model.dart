import 'auth_session_model.dart';

class RegistrationStatus {
  final String status;
  final String code;
  final String message;
  final List<SessionPharmacy> pharmacies;

  const RegistrationStatus({
    required this.status,
    required this.code,
    required this.message,
    required this.pharmacies,
  });

  bool get isApproved => status == 'approved';

  factory RegistrationStatus.fromJson(Map<String, dynamic> json) {
    final pharmacies = json['pharmacies'] as List<dynamic>? ?? [];

    return RegistrationStatus(
      status: json['status'] as String,
      code: json['code'] as String,
      message: json['message'] as String,
      pharmacies: pharmacies
          .map(
            (item) => SessionPharmacy.fromJson(
              Map<String, dynamic>.from(item as Map),
            ),
          )
          .toList(growable: false),
    );
  }
}

class PharmacistRegistrationResult {
  final String statusToken;
  final RegistrationStatus registration;

  const PharmacistRegistrationResult({
    required this.statusToken,
    required this.registration,
  });

  factory PharmacistRegistrationResult.fromJson(Map<String, dynamic> json) {
    final data = Map<String, dynamic>.from(json['data'] as Map);

    return PharmacistRegistrationResult(
      statusToken: data['registration_status_token'] as String,
      registration: RegistrationStatus.fromJson(
        Map<String, dynamic>.from(data['registration'] as Map),
      ),
    );
  }
}
