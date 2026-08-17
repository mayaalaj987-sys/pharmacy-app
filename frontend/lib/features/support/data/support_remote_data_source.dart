import 'package:dio/dio.dart';

abstract class SupportRemoteDataSource {
  Future<Response<dynamic>> getTickets();

  Future<Response<dynamic>> createTicket(Map<String, dynamic> data);
}
