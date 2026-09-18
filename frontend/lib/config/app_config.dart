import 'package:flutter_dotenv/flutter_dotenv.dart';

/// Global app configuration loaded from the `.env` file.
class AppConfig {
  const AppConfig._();

  static Future<void> load() => dotenv.load(fileName: '.env');

  /// Base URL used for every request to the Laravel backend.
  static String get backendUrl {
    final url = dotenv.env['backend_url'];
    if (url == null || url.trim().isEmpty) {
      throw StateError('backend_url is not defined in the .env file.');
    }
    return url.replaceFirst(RegExp(r'/$'), '');
  }
}
