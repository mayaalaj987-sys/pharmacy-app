import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/support/data/support_remote_data_source.dart';
import 'package:phamacy_managment/features/support/data/support_repository.dart';
import 'package:phamacy_managment/features/support/presentation/cubit/support_cubit.dart';
import 'package:phamacy_managment/features/support/presentation/cubit/support_state.dart';

void main() {
  test('fetchTickets parses the envelope and the admin reply', () async {
    final tickets = await SupportRepository(FakeSupportApi()).fetchTickets();

    expect(tickets, hasLength(2));

    final open = tickets.first;
    expect(open.subject, 'Cannot receive an order');
    expect(open.isOpen, isTrue);
    expect(open.hasResponse, isFalse);
    expect(open.statusLabel, 'Awaiting reply');

    final answered = tickets.last;
    expect(answered.isOpen, isFalse);
    expect(answered.hasResponse, isTrue);
    expect(answered.adminResponse, 'Update to the latest build.');
    expect(answered.statusLabel, 'Answered');
    expect(answered.respondedAt, isNotNull);
  });

  test('createTicket sends only the subject and message', () async {
    final api = FakeSupportApi();

    await SupportRepository(
      api,
    ).createTicket(subject: 'Help', message: 'Something is wrong with sales.');

    // Sender identity and status belong to the server; sending them is rejected.
    expect(api.lastPayload, {
      'subject': 'Help',
      'message': 'Something is wrong with sales.',
    });
    expect(api.lastPayload!.containsKey('pharmacist_id'), isFalse);
    expect(api.lastPayload!.containsKey('employee_id'), isFalse);
    expect(api.lastPayload!.containsKey('status'), isFalse);
  });

  test('load exposes ready state with the open count', () async {
    final cubit = SupportCubit(SupportRepository(FakeSupportApi()));

    await cubit.load();

    expect(cubit.state.status, SupportStatus.ready);
    expect(cubit.state.tickets, hasLength(2));
    expect(cubit.state.openCount, 1);
    await cubit.close();
  });

  test('sending refetches so the new ticket arrives with its id', () async {
    final api = FakeSupportApi();
    final cubit = SupportCubit(SupportRepository(api));

    final ok = await cubit.send(
      subject: 'Help',
      message: 'A long enough message.',
    );

    expect(ok, isTrue);
    expect(api.listCalls, 1);
    expect(cubit.state.sending, isFalse);
    expect(cubit.state.tickets, hasLength(2));
    await cubit.close();
  });

  test(
    'a rejected send surfaces the backend error and sends nothing else',
    () async {
      final api = FakeSupportApi()..failCreate = true;
      final cubit = SupportCubit(SupportRepository(api));

      final ok = await cubit.send(subject: 'Hi', message: 'too short');

      expect(ok, isFalse);
      expect(cubit.state.sending, isFalse);
      expect(cubit.state.error, isNotNull);
      expect(cubit.state.error!.statusCode, 422);
      // A failed send must not trigger a reload.
      expect(api.listCalls, 0);
      await cubit.close();
    },
  );

  test('a load failure surfaces an error state', () async {
    final cubit = SupportCubit(
      SupportRepository(FakeSupportApi()..failList = true),
    );

    await cubit.load();

    expect(cubit.state.status, SupportStatus.failure);
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });
}

class FakeSupportApi implements SupportRemoteDataSource {
  Map<String, dynamic>? lastPayload;
  int listCalls = 0;
  bool failCreate = false;
  bool failList = false;

  @override
  Future<Response<dynamic>> getTickets() async {
    listCalls++;
    if (failList) {
      throw DioException(
        requestOptions: RequestOptions(path: '/support/tickets'),
        type: DioExceptionType.connectionError,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/support/tickets'),
      data: {
        'open_count': 1,
        'tickets': [
          {
            'id': 2,
            'subject': 'Cannot receive an order',
            'message': 'Receiving an order does not add the stock.',
            'status': 'open',
            'admin_response': null,
            'responded_at': null,
            'created_at': '2026-08-17T09:00:00.000Z',
          },
          {
            'id': 1,
            'subject': 'Signed out after a sale',
            'message': 'The app signed me out right after a sale.',
            'status': 'resolved',
            'admin_response': 'Update to the latest build.',
            'responded_at': '2026-08-17T10:00:00.000Z',
            'created_at': '2026-08-16T09:00:00.000Z',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> createTicket(Map<String, dynamic> data) async {
    lastPayload = data;
    if (failCreate) {
      throw DioException(
        requestOptions: RequestOptions(path: '/support/tickets'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/support/tickets'),
          statusCode: 422,
          data: {
            'message': 'The given data was invalid.',
            'code': 'validation_failed',
            'errors': {
              'message': ['The message field must be at least 10 characters.'],
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/support/tickets'),
      statusCode: 201,
      data: {'code': 'support_ticket_created'},
    );
  }
}
