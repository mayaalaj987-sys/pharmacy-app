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
        'profile_image': 'https://example.test/profile.jpg',
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
      },
      'access': {
        'operational': true,
        'code': 'ready',
        'requires_active_pharmacy': false,
      },
    });

    expect(session.actor.type, AuthActorType.pharmacist);
    expect(session.actor.profileImage, 'https://example.test/profile.jpg');
    expect(session.availablePharmacies.first.status, 'rejected');
    expect(session.approvedPharmacies.single.id, 2);
    expect(session.activePharmacy?.id, 2);
    expect(session.access.operational, isTrue);
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
