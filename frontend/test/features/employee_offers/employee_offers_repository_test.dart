import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/employee_offers/data/employee_offers_remote_data_source.dart';
import 'package:phamacy_managment/features/employee_offers/data/employee_offers_repository.dart';
import 'package:phamacy_managment/features/employee_offers/presentation/cubit/employee_offers_cubit.dart';
import 'package:phamacy_managment/features/employee_offers/presentation/cubit/employee_offers_state.dart';

void main() {
  test('an offer carries everything needed to weigh it up', () async {
    final inbox = await EmployeeOffersRepository(FakeOffersApi()).fetchInbox();

    expect(inbox.offers, hasLength(2));
    final live = inbox.offers.first;
    expect(live.shift, 'evening');
    expect(live.shiftLabel, 'Evening');
    expect(live.salary, 500000.0);
    expect(live.acceptable, isTrue);
    expect(live.unavailableReason, isNull);
    expect(live.pharmacy!.name, 'Barada Pharmacy');
    expect(live.pharmacy!.address, 'Al-Mazzeh, Damascus');
    expect(live.pharmacy!.hasLocation, isTrue);
    expect(live.owner!.name, 'Ahmad Alhaj');

    // The pharmacy made the first move, so its owner is reachable.
    expect(live.owner!.contact, '0933111222');
  });

  test('the owner falls back to an email when there is no phone', () async {
    final inbox = await EmployeeOffersRepository(FakeOffersApi()).fetchInbox();

    expect(inbox.offers.last.owner!.phone, isNull);
    expect(inbox.offers.last.owner!.contact, 'owner@example.test');
  });

  test('an unacceptable offer explains itself in plain words', () async {
    final inbox = await EmployeeOffersRepository(FakeOffersApi()).fetchInbox();
    final held = inbox.offers.last;

    // Still pending and still listed — offers are a record, not a queue.
    expect(held.status, 'pending');
    expect(held.acceptable, isFalse);
    expect(held.unavailableReason, 'already_employed');
    expect(
      held.unavailableExplanation,
      'You are currently employed. Leave your job to accept this.',
    );
  });

  test('actionable counts what can be accepted, not what is pending', () async {
    final cubit = _cubit(FakeOffersApi());

    await cubit.load();

    expect(cubit.state.status, EmployeeOffersStatus.ready);
    expect(cubit.state.offers, hasLength(2));
    expect(cubit.state.actionable, 1);
    expect(cubit.state.isEmployed, isTrue);
    expect(cubit.state.employment!.pharmacyName, 'Barada Pharmacy');
    expect(cubit.state.employment!.shift, 'morning');
    await cubit.close();
  });

  test('an empty inbox is a state, not an error', () async {
    final cubit = _cubit(FakeOffersApi()..empty = true);

    await cubit.load();

    expect(cubit.state.status, EmployeeOffersStatus.ready);
    expect(cubit.state.offers, isEmpty);
    expect(cubit.state.actionable, 0);
    expect(cubit.state.isEmployed, isFalse);
    await cubit.close();
  });

  test('accepting reloads the session, because the whole app changes', () async {
    sessionReloads = 0;
    final api = FakeOffersApi();
    final cubit = _cubit(api);
    await cubit.load();

    expect(await cubit.accept(1), isTrue);

    expect(api.accepted, [1]);
    // Without this the app would stay on the unattached shell after being
    // hired: AuthGate routes off the session, not off this list.
    expect(sessionReloads, 1);
    expect(cubit.state.accepting, isFalse);
    await cubit.close();
  });

  test('a refused acceptance surfaces the reason and reloads nothing', () async {
    sessionReloads = 0;
    final api = FakeOffersApi()..failAccept = true;
    final cubit = _cubit(api);
    await cubit.load();

    expect(await cubit.accept(1), isFalse);

    expect(api.accepted, isEmpty);
    expect(sessionReloads, 0);
    expect(cubit.state.error!.code, 'shift_taken');
    // The list stays on screen; the refusal is nearly always the world having
    // moved on, and the reason is what the applicant needs to see.
    expect(cubit.state.offers, hasLength(2));
    expect(cubit.state.accepting, isFalse);
    await cubit.close();
  });

  test('a second tap while accepting is ignored', () async {
    sessionReloads = 0;
    final cubit = _cubit(FakeOffersApi());
    await cubit.load();

    final results = await Future.wait([cubit.accept(1), cubit.accept(1)]);

    expect(results, containsAll(<bool>[true, false]));
    expect(sessionReloads, 1);
    await cubit.close();
  });

  test('resigning reloads the session and refreshes the offers', () async {
    sessionReloads = 0;
    final api = FakeOffersApi();
    final cubit = _cubit(api);
    await cubit.load();

    expect(await cubit.resign(), isTrue);

    expect(api.resigned, 1);
    // Employment decides which shell the app shows, so the session must be
    // reloaded rather than patched here.
    expect(sessionReloads, 1);
    await cubit.close();
  });

  test('resigning without a job surfaces the refusal', () async {
    sessionReloads = 0;
    final api = FakeOffersApi()..failResign = true;
    final cubit = _cubit(api);
    await cubit.load();

    expect(await cubit.resign(), isFalse);

    expect(sessionReloads, 0);
    expect(cubit.state.error!.code, 'not_employed');
    await cubit.close();
  });

  test('a failure surfaces without wiping what was already shown', () async {
    final api = FakeOffersApi();
    final cubit = _cubit(api);
    await cubit.load();
    expect(cubit.state.offers, hasLength(2));

    api.fail = true;
    await cubit.load();

    expect(cubit.state.status, EmployeeOffersStatus.failure);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.offers, hasLength(2));
    await cubit.close();
  });
}

/// Accepting reloads the auth session, because that is what swaps the
/// unattached shell for the working one. Counted rather than mocked away, so a
/// test can assert it actually happened.
int sessionReloads = 0;

EmployeeOffersCubit _cubit(FakeOffersApi api) {
  return EmployeeOffersCubit(
    EmployeeOffersRepository(api),
    () async => sessionReloads++,
  );
}

class FakeOffersApi implements EmployeeOffersRemoteDataSource {
  bool empty = false;
  bool fail = false;
  bool failAccept = false;
  bool failResign = false;
  final List<int> accepted = [];
  int resigned = 0;

  @override
  Future<Response<dynamic>> getOffers() async {
    final options = RequestOptions(path: '/employee/offers');

    if (fail) {
      throw DioException(
        requestOptions: options,
        response: Response<dynamic>(
          requestOptions: options,
          statusCode: 500,
          data: {'message': 'boom'},
        ),
      );
    }

    return Response<dynamic>(
      requestOptions: options,
      data: empty
          ? {
              'offers': <dynamic>[],
              'counts': {'actionable': 0, 'total': 0},
              'employment': null,
            }
          : {
              'offers': [
                {
                  'id': 1,
                  'status': 'pending',
                  'shift': 'evening',
                  'salary': 500000,
                  'offered_at': '2026-08-18T09:00:00.000Z',
                  'acceptable': true,
                  'unavailable_reason': null,
                  'pharmacy': {
                    'id': 7,
                    'name': 'Barada Pharmacy',
                    'address': 'Al-Mazzeh, Damascus',
                    'latitude': 33.5138,
                    'longitude': 36.2765,
                  },
                  'owner': {
                    'name': 'Ahmad Alhaj',
                    'phone': '0933111222',
                    'email': 'ahmad@example.test',
                  },
                },
                {
                  'id': 2,
                  'status': 'pending',
                  'shift': 'morning',
                  'salary': null,
                  'offered_at': '2026-08-17T09:00:00.000Z',
                  'acceptable': false,
                  'unavailable_reason': 'already_employed',
                  'pharmacy': {
                    'id': 8,
                    'name': 'Al-Shahba Pharmacy',
                    'address': 'Al-Furqan, Aleppo',
                    'latitude': null,
                    'longitude': null,
                  },
                  'owner': {
                    'name': 'Rana Saeed',
                    'phone': null,
                    'email': 'owner@example.test',
                  },
                },
              ],
              'counts': {'actionable': 1, 'total': 2},
              'employment': {
                'pharmacy_id': 7,
                'pharmacy_name': 'Barada Pharmacy',
                'shift': 'morning',
              },
            },
    );
  }

  @override
  Future<Response<dynamic>> acceptOffer(int id) async {
    final options = RequestOptions(path: '/employee/offers/$id/accept');

    if (failAccept) {
      throw DioException(
        requestOptions: options,
        response: Response<dynamic>(
          requestOptions: options,
          statusCode: 409,
          data: {'message': 'taken', 'code': 'shift_taken'},
        ),
      );
    }

    accepted.add(id);
    return Response<dynamic>(
      requestOptions: options,
      data: {'message': 'ok', 'code': 'offer_accepted'},
    );
  }

  @override
  Future<Response<dynamic>> resign() async {
    final options = RequestOptions(path: '/employee/resign');

    if (failResign) {
      throw DioException(
        requestOptions: options,
        response: Response<dynamic>(
          requestOptions: options,
          statusCode: 409,
          data: {'message': 'no job', 'code': 'not_employed'},
        ),
      );
    }

    resigned++;
    return Response<dynamic>(
      requestOptions: options,
      data: {'message': 'ok', 'code': 'employment_ended'},
    );
  }
}
