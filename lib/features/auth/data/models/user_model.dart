class UserModel {
  final int id;
  final String name;
  final String email;
  final String? profile;
  final String status;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.profile,
    this.status = "pending",
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'],
      name: json['name'],
      email: json['email'],
      profile: json['profile'],
      status: json['status'] ?? "pending",
    );
  }

  /// مسار الصورة كامل (إذا الباك بيرجع نسبي فقط)
  String? get imagePath {
    if (profile == null) return null;
    return "http://10.0.2.2:8000/storage/$profile";
  }
}