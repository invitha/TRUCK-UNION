import 'dart:convert';
import 'dart:io';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;

/// Mirrors the working customer-app EnhancedFCMService.
/// Call [initializeForVendor] after Firebase Auth login.
/// Call [initializeForDriver] after password-based driver login.
class VendorFcmService {
  static const _baseUrl = 'https://crm.abra-logistic.com/api1';

  // ── Public entry points ────────────────────────────────────────────────────

  /// Called after vendor (Firebase Auth) login succeeds.
  static Future<void> initializeForVendor(String firebaseUid) async {
    final token = await _freshToken();
    if (token == null) return;
    await _save(firebaseUid, token);
    _setupMessageHandlers(firebaseUid: firebaseUid);
  }

  /// Called after driver (password) login succeeds.
  static Future<void> initializeForDriver(String vehicleId) async {
    final token = await _freshToken();
    if (token == null) return;
    final driverUid = 'driver_$vehicleId';
    await _save(driverUid, token);
    _setupMessageHandlers(driverUid: driverUid);
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  /// Delete old token, generate a fresh one and return it.
  /// Any non-null token ≥ 20 chars is accepted — length is not a reliable
  /// indicator of validity; freshness (after deleteToken) is what matters.
  static Future<String?> _freshToken() async {
    try {
      // Delete cached token so Firebase issues a brand-new one
      try { await FirebaseMessaging.instance.deleteToken(); } catch (_) {}
      await Future.delayed(const Duration(seconds: 3));

      // Up to 5 attempts to get a non-null fresh token
      for (int i = 1; i <= 5; i++) {
        final token = await FirebaseMessaging.instance.getToken();
        if (token != null && token.length > 20) {
          return token; // fresh token — length doesn't matter, it was just regenerated
        }
        await Future.delayed(Duration(seconds: i * 2));
      }
    } catch (_) {}
    return null;
  }

  /// Save FCM token to server.
  static Future<void> _save(String uid, String token) async {
    try {
      await http.post(
        Uri.parse('$_baseUrl/vendor/save_fcm_token.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'firebase_uid': uid,
          'fcm_token': token,
          'platform': Platform.isIOS ? 'ios' : 'android',
        }),
      ).timeout(const Duration(seconds: 20));
    } catch (_) {}
  }

  /// Listen for token refresh — keep server in sync.
  static void _setupMessageHandlers({String? firebaseUid, String? driverUid}) {
    FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
      if (newToken.length < 20) return; // skip empty/null-equivalent tokens
      if (firebaseUid != null) await _save(firebaseUid, newToken);
      if (driverUid    != null) await _save(driverUid,  newToken);

      // Also update vendor if we're in a driver session via shared prefs
      if (driverUid == null) {
        final user = FirebaseAuth.instance.currentUser;
        if (user != null) await _save(user.uid, newToken);
      }
    });
  }

  /// On every app open (splash), just save if token is valid — no deletion.
  static Future<void> refreshOnSplash() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.length < 20) return; // skip null/empty tokens only

      final prefs = await SharedPreferences.getInstance();

      // Vendor session
      final user = FirebaseAuth.instance.currentUser;
      if (user != null) await _save(user.uid, token);

      // Driver session
      final driverJson = prefs.getString('driver_session');
      if (driverJson != null) {
        try {
          final d = jsonDecode(driverJson);
          final vid = d['driver']?['vehicle_id']?.toString() ??
                      d['driver']?['id']?.toString() ?? '';
          if (vid.isNotEmpty) await _save('driver_$vid', token);
        } catch (_) {}
      }
    } catch (_) {}
  }
}
