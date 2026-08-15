import 'dart:async';

class AuthSessionEvents {
  final StreamController<void> _invalidated =
      StreamController<void>.broadcast();

  Stream<void> get invalidated => _invalidated.stream;

  void notifyInvalidated() => _invalidated.add(null);

  Future<void> dispose() => _invalidated.close();
}
