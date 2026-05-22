
import 'package:dio/dio.dart';
import 'package:phamacy_managment/core/network/api_constants.dart';
import 'package:phamacy_managment/core/network/dio_client.dart';

class AuthRemoteDatasource {

  final Dio dio = DioClient.dio;

  Future<Response> registerStep1({
    required String username,
    required String email,
    required String password,
    required String imagePath,
  }) async {

    FormData formData = FormData.fromMap({
      'username': username,
      'email': email,
      'password': password,
      'profile_image':
      await MultipartFile.fromFile(imagePath),
    });

    return await dio.post(
      ApiConstants.register,
      data: formData,
    );
  }

  Future<Response> registerStep2({
    required int userId,
    required String pharmacyName,
    required String pharmacyLocation,
    required String documentPath,
  }) async {

    FormData formData = FormData.fromMap({
      'user_id': userId,
      'pharmacy_name': pharmacyName,
      'pharmacy_location': pharmacyLocation,
      'pharmacy_document':
      await MultipartFile.fromFile(documentPath),
    });

    return await dio.post(
      ApiConstants.pharmacyRegister,
      data: formData,
    );
  }

  Future<Response> login({
    required String username,
    required String password,
  }) async {

    return await dio.post(
      ApiConstants.login,
      data: {
        'username': username,
        'password': password,
      },
    );
  }
}