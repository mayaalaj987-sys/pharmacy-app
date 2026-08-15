class UserModel {
  final int id;
  final String name;
  final String email;
  final String? profileImage;
  final String status;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.profileImage,
    this.status = "pending",
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'],
      name: json['name'],
      email: json['email'],
      profileImage: json['profile_image'] as String?,
      status: json['status'] ?? "pending",
    );
  }

  String? get imagePath => profileImage;
}
