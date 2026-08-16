import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/auth/data/models/auth_session_model.dart';

void main() {
  test('parses pharmacist session and pharmacy statuses', () {
    final session = AuthSession.fromJson({
      'actor': {
        'id': 4,
        'type': 'pharmacist',
        'role': 'owner',
        'status': null,
        'name': 'Owner',
        'email': 'owner@example.test',
        'phone': '0999000000',
        'profile_image_url': 'https://example.test/profile.jpg',
      },
      'available_pharmacies': [
        {'id': 1, 'name': 'One', 'address': 'A', 'status': 'rejected'},
        {'id': 2, 'name': 'Two', 'address': 'B', 'status': 'approved'},
      ],
      'active_pharmacy': {
        'id': 2,
        'name': 'Two',
        'address': 'B',
        'status': 'approved',
        'latitude': 33.5138,
        'longitude': 36.2765,
        'certificate_on_file': true,
        'license_on_file': true,
      },
      'access': {
        'operational': true,
        'code': 'ready',
        'requires_active_pharmacy': false,
      },
    });

    expect(session.actor.type, AuthActorType.pharmacist);
    expect(session.actor.profileImage, 'https://example.test/profile.jpg');
    expect(session.actor.phone, '0999000000');
    expect(session.availablePharmacies.first.status, 'rejected');
    expect(session.approvedPharmacies.single.id, 2);
    expect(session.activePharmacy?.id, 2);
    expect(session.activePharmacy?.latitude, 33.5138);
    expect(session.activePharmacy?.longitude, 36.2765);
    expect(session.activePharmacy?.certificateOnFile, isTrue);
    expect(session.activePharmacy?.licenseOnFile, isTrue);
    expect(session.access.operational, isTrue);
  });

  test('normalizes empty profile image URLs and nullable coordinates', () {
    final session = AuthSession.fromJson({
      'actor': {
        'id': 4,
        'type': 'pharmacist',
        'role': 'owner',
        'status': null,
        'name': 'Owner',
        'email': 'owner@example.test',
        'phone': null,
        'profile_image_url': '   ',
      },
      'available_pharmacies': [
        {
          'id': 1,
          'name': 'One',
          'address': 'A',
          'status': 'approved',
          'latitude': null,
          'longitude': null,
        },
      ],
      'active_pharmacy': {
        'id': 1,
        'name': 'One',
        'address': 'A',
        'status': 'approved',
        'latitude': null,
        'longitude': null,
      },
      'access': {
        'operational': true,
        'code': 'ready',
        'requires_active_pharmacy': false,
      },
    });

    expect(session.actor.profileImageUrl, isNull);
    expect(session.activePharmacy?.latitude, isNull);
    expect(session.activePharmacy?.longitude, isNull);
  });

  test('parses restricted employee session with nullable pharmacy', () {
    final session = AuthSession.fromJson({
      'actor': {
        'id': 9,
        'type': 'employee',
        'role': 'trainee',
        'status': 'pending',
        'name': 'Trainee',
        'email': 'trainee@example.test',
        'profile_image': null,
      },
      'available_pharmacies': [],
      'active_pharmacy': null,
      'access': {
        'operational': false,
        'code': 'account_pending',
        'requires_active_pharmacy': false,
      },
    });

    expect(session.actor.type, AuthActorType.employee);
    expect(session.actor.role, 'trainee');
    expect(session.activePharmacy, isNull);
    expect(session.access.code, 'account_pending');
  });
}
