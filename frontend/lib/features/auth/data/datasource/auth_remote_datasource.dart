// import 'package:dio/dio.dart';
//
// import '../../../../core/network/dio_client.dart';
// import '../../../../core/network/api_constants.dart';
//
// class AuthService {
//   Future<Response> registerPharmacist({
//     required String username,
//     required String email,
//     required String password,
//   }) async {
//     return await DioClient.dio.post(
//       ApiConstants.registerPharmacist,
//       data: {
//         "username": username,
//         "email": email,
//         "password": password,
//       },
//     );
//   }
//
//   Future<Response> login(String email, String password) async {
//     return await DioClient.dio.post(
//       ApiConstants.login,
//       data: {
//         "email": email,
//         "password": password,
//       },
//     );
//   }
// }
