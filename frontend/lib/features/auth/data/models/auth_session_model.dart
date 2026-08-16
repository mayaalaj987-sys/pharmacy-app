enum AuthActorType { pharmacist, employee }

class AuthActor {
  final int id;
  final AuthActorType type;
  final String role;
  final String? status;
  final String name;
  final String email;
  final String? phone;
  final String? profileImageUrl;

  const AuthActor({
    required this.id,
    required this.type,
    required this.role,
    required this.status,
    required this.name,
    required this.email,
    this.phone,
    this.profileImageUrl,
  });

  String? get profileImage => profileImageUrl;

  factory AuthActor.fromJson(Map<String, dynamic> json) {
    return AuthActor(
      id: (json['id'] as num).toInt(),
      type: json['type'] == 'employee'
          ? AuthActorType.employee
          : AuthActorType.pharmacist,
      role: json['role'] as String,
      status: json['status'] as String?,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      profileImageUrl: _nonEmptyString(
        json['profile_image_url'] ?? json['profile_image'],
      ),
    );
  }

  static String? _nonEmptyString(dynamic value) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? null : text;
  }
}

class SessionPharmacy {
  final int id;
  final String name;
  final String address;
  final String status;
  final double? latitude;
  final double? longitude;
  final bool certificateOnFile;
  final bool licenseOnFile;

  const SessionPharmacy({
    required this.id,
    required this.name,
    required this.address,
    required this.status,
    this.latitude,
    this.longitude,
    this.certificateOnFile = false,
    this.licenseOnFile = false,
  });

  bool get isApproved => status == 'approved';

  factory SessionPharmacy.fromJson(Map<String, dynamic> json) {
    return SessionPharmacy(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String,
      status: json['status'] as String,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      certificateOnFile: json['certificate_on_file'] as bool? ?? false,
      licenseOnFile: json['license_on_file'] as bool? ?? false,
    );
  }
}

class SessionAccess {
  final bool operational;
  final String code;
  final bool requiresActivePharmacy;

  const SessionAccess({
    required this.operational,
    required this.code,
    required this.requiresActivePharmacy,
  });

  factory SessionAccess.fromJson(Map<String, dynamic> json) {
    return SessionAccess(
      operational: json['operational'] as bool,
      code: json['code'] as String,
      requiresActivePharmacy:
          json['requires_active_pharmacy'] as bool? ?? false,
    );
  }
}

class AuthSession {
  final AuthActor actor;
  final List<SessionPharmacy> availablePharmacies;
  final SessionPharmacy? activePharmacy;
  final SessionAccess access;

  const AuthSession({
    required this.actor,
    required this.availablePharmacies,
    required this.activePharmacy,
    required this.access,
  });

  List<SessionPharmacy> get approvedPharmacies =>
      availablePharmacies.where((pharmacy) => pharmacy.isApproved).toList();

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final pharmacies = json['available_pharmacies'] as List<dynamic>? ?? [];
    final active = json['active_pharmacy'];

    return AuthSession(
      actor: AuthActor.fromJson(json['actor'] as Map<String, dynamic>),
      availablePharmacies: pharmacies
          .map((item) => SessionPharmacy.fromJson(item as Map<String, dynamic>))
          .toList(growable: false),
      activePharmacy: active is Map<String, dynamic>
          ? SessionPharmacy.fromJson(active)
          : null,
      access: SessionAccess.fromJson(json['access'] as Map<String, dynamic>),
    );
  }
}

class AuthLoginResult {
  final String token;
  final AuthSession session;

  const AuthLoginResult({required this.token, required this.session});

  factory AuthLoginResult.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return AuthLoginResult(
      token: data['token'] as String,
      session: AuthSession.fromJson(data['session'] as Map<String, dynamic>),
    );
  }
}
