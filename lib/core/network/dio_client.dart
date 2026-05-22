//منعمل ال dio مرة وحدة هون مشان نضل نستدعيه بعدين استدعاء ما نضطر نكتبو كامل
import 'package:dio/dio.dart';

class DioClient {

  static final Dio dio = Dio(
    BaseOptions(
      headers: {
        'Accept': 'application/json',
      },
    ),
  );
}