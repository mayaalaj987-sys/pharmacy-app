import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/support_ticket.dart';
import 'support_remote_data_source.dart';

class SupportRepository {
  final SupportRemoteDataSource api;

  const SupportRepository(this.api);

  Future<List<SupportTicket>> fetchTickets() async {
    try {
      final response = await api.getTickets();
      final data = response.data;
      if (data is! Map<String, dynamic>) return const <SupportTicket>[];
      final raw = data['tickets'];

      return raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(SupportTicket.fromJson)
                .toList(growable: false)
          : const <SupportTicket>[];
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Only the subject and message are sent; the backend rejects any attempt to
  /// name a different sender or pre-set the status.
  Future<void> createTicket({
    required String subject,
    required String message,
  }) async {
    try {
      await api.createTicket({'subject': subject, 'message': message});
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
