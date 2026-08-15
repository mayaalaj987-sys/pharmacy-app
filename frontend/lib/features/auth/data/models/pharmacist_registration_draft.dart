import 'dart:io';

class PharmacistRegistrationDraft {
  final String name;
  final String email;
  final String password;
  final File? profileImage;

  const PharmacistRegistrationDraft({
    required this.name,
    required this.email,
    required this.password,
    required this.profileImage,
  });
}
