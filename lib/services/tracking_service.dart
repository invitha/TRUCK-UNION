import 'package:flutter/foundation.dart';

/// Tracking service — stubbed for iOS 26 compatibility.
///
/// firebase_analytics and facebook_app_events both use Swift plugins
/// that are incompatible with iOS 26. All event logging is a no-op
/// until Flutter releases iOS 26-compatible binaries.
class TrackingService {
  static final TrackingService _instance = TrackingService._internal();
  factory TrackingService() => _instance;
  TrackingService._internal();

  bool _initialized = false;

  Future<void> initialize() async {
    _initialized = true;
    debugPrint('✅ TrackingService: Initialized (stubbed — iOS 26 compatibility mode)');
  }

  Future<void> logRegistration({String method = 'email'}) async => _noop('sign_up');
  Future<void> logLogin({String method = 'email'}) async => _noop('login');
  Future<void> logScreenView(String screenName) async => _noop('screen_view');
  Future<void> logViewVehicle(String vehicleType, {String? vehicleId}) async => _noop('view_item');
  Future<void> logKYCStarted() async => _noop('kyc_started');
  Future<void> logKYCSubmitted() async => _noop('kyc_submitted');
  Future<void> logKYCVerified() async => _noop('kyc_verified');
  Future<void> logVehicleAdded(String vehicleType) async => _noop('vehicle_added');
  Future<void> logFleetAssigned() async => _noop('fleet_assigned');
  Future<void> logOrderCompleted({required double amount, String currency = 'INR', String? orderId}) async => _noop('purchase');
  Future<void> logPaymentInitiated(double amount) async => _noop('begin_checkout');
  Future<void> logSupportContacted(String method) async => _noop('support_contacted');
  Future<void> logNotificationViewed() async => _noop('notification_viewed');
  Future<void> logEvent(String eventName, {Map<String, Object>? parameters}) async => _noop(eventName);
  Future<void> setUserId(String userId) async => _noop('set_user_id');
  Future<void> setUserProperties({String? userType, String? kycStatus, String? vehicleCount}) async {}

  void _noop(String event) {
    debugPrint('📊 Tracking (stubbed): $event');
  }
}

// Global singleton for easy access throughout the app
final trackingService = TrackingService();
