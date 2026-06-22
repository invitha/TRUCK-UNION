import 'package:flutter/foundation.dart';
import 'package:firebase_analytics/firebase_analytics.dart';

/// Unified tracking service using Firebase Analytics.
///
/// Facebook App Events removed — incompatible with iOS 26 (swift_getObjectType crash).
/// All events are logged to Firebase Analytics only.
class TrackingService {
  static final TrackingService _instance = TrackingService._internal();
  factory TrackingService() => _instance;
  TrackingService._internal();

  final FirebaseAnalytics _firebase = FirebaseAnalytics.instance;

  bool _initialized = false;

  // ─── Initialization ──────────────────────────────────────────────────────

  Future<void> initialize() async {
    try {
      _initialized = true;
      debugPrint('✅ TrackingService: Initialized (Firebase only)');
    } catch (e) {
      debugPrint('⚠️ TrackingService Error during init: $e');
    }
  }

  // ─── Core Auth Events ─────────────────────────────────────────────────────

  Future<void> logRegistration({String method = 'email'}) async {
    await _log('sign_up', {'method': method});
  }

  Future<void> logLogin({String method = 'email'}) async {
    await _log('login', {'method': method});
  }

  // ─── Content / Screen Events ─────────────────────────────────────────────

  Future<void> logScreenView(String screenName) async {
    await _log('screen_view', {'screen_name': screenName});
  }

  Future<void> logViewVehicle(String vehicleType, {String? vehicleId}) async {
    await _log('view_item', {
      'item_category': 'vehicle',
      'item_id': vehicleId ?? vehicleType,
      'item_name': vehicleType,
    });
  }

  // ─── KYC / Onboarding Events ──────────────────────────────────────────────

  Future<void> logKYCStarted() async {
    await _log('kyc_started');
  }

  Future<void> logKYCSubmitted() async {
    await _log('kyc_submitted');
  }

  Future<void> logKYCVerified() async {
    await _log('kyc_verified');
  }

  // ─── Vehicle / Fleet Events ───────────────────────────────────────────────

  Future<void> logVehicleAdded(String vehicleType) async {
    await _log('vehicle_added', {'vehicle_type': vehicleType});
  }

  Future<void> logFleetAssigned() async {
    await _log('fleet_assigned');
  }

  // ─── Order / Revenue Events ───────────────────────────────────────────────

  Future<void> logOrderCompleted({
    required double amount,
    String currency = 'INR',
    String? orderId,
  }) async {
    await _log('purchase', {
      'value': amount,
      'currency': currency,
      if (orderId != null) 'transaction_id': orderId,
    });
  }

  Future<void> logPaymentInitiated(double amount) async {
    await _log('begin_checkout', {'value': amount, 'currency': 'INR'});
  }

  // ─── Support / Engagement Events ─────────────────────────────────────────

  Future<void> logSupportContacted(String method) async {
    await _log('support_contacted', {'method': method});
  }

  Future<void> logNotificationViewed() async {
    await _log('notification_viewed');
  }

  // ─── Generic / Custom Events ──────────────────────────────────────────────

  Future<void> logEvent(
    String eventName, {
    Map<String, Object>? parameters,
  }) async {
    await _log(eventName, parameters);
  }

  // ─── User Identity ────────────────────────────────────────────────────────

  Future<void> setUserId(String userId) async {
    try {
      await _firebase.setUserId(id: userId);
      debugPrint('📊 Tracking: User ID set');
    } catch (e) {
      debugPrint('⚠️ Tracking setUserId error: $e');
    }
  }

  Future<void> setUserProperties({
    String? userType,
    String? kycStatus,
    String? vehicleCount,
  }) async {
    try {
      if (userType != null) {
        await _firebase.setUserProperty(name: 'user_type', value: userType);
      }
      if (kycStatus != null) {
        await _firebase.setUserProperty(name: 'kyc_status', value: kycStatus);
      }
      if (vehicleCount != null) {
        await _firebase.setUserProperty(name: 'vehicle_count', value: vehicleCount);
      }
    } catch (e) {
      debugPrint('⚠️ Tracking setUserProperties error: $e');
    }
  }

  // ─── Private Helper ───────────────────────────────────────────────────────

  Future<void> _log(String eventName, [Map<String, Object>? params]) async {
    if (!_initialized) {
      debugPrint('⚠️ TrackingService not initialized — call initialize() first');
    }
    try {
      await _firebase.logEvent(name: eventName, parameters: params);
      debugPrint('📊 Tracked: $eventName');
    } catch (e) {
      debugPrint('⚠️ Firebase tracking error ($eventName): $e');
    }
  }
}

// Global singleton for easy access throughout the app
final trackingService = TrackingService();
