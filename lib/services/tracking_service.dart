import 'package:flutter/foundation.dart';
import 'package:facebook_app_events/facebook_app_events.dart';
import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:app_tracking_transparency/app_tracking_transparency.dart';

/// Unified tracking service for Facebook Meta SDK + Firebase Analytics.
///
/// Used for digital marketing campaigns, retargeting, lookalike audiences,
/// and conversion measurement across Facebook/Instagram Ads.
///
/// Usage:
///   trackingService.logRegistration(method: 'email');
///   trackingService.logLogin(method: 'google');
///   trackingService.logScreenView('vendor_dashboard');
class TrackingService {
  static final TrackingService _instance = TrackingService._internal();
  factory TrackingService() => _instance;
  TrackingService._internal();

  final FacebookAppEvents _facebook = FacebookAppEvents();
  final FirebaseAnalytics _firebase = FirebaseAnalytics.instance;

  bool _initialized = false;

  // ─── Initialization ──────────────────────────────────────────────────────

  Future<void> initialize() async {
    try {
      // Skip all native SDK calls on web — Facebook SDK is mobile-only
      if (kIsWeb) {
        debugPrint('📊 TrackingService: Web platform — skipping native SDK init');
        _initialized = true;
        return;
      }

      // 1. Request iOS App Tracking Transparency (required for IDFA on iOS 14+)
      if (defaultTargetPlatform == TargetPlatform.iOS) {
        var currentStatus = await AppTrackingTransparency.trackingAuthorizationStatus;

        // Only request if not yet determined — avoids dialog on re-launch
        if (currentStatus == TrackingStatus.notDetermined) {
          // A short delay gives the Flutter engine time to render the UI completely
          await Future.delayed(const Duration(milliseconds: 1000));
          currentStatus = await AppTrackingTransparency.requestTrackingAuthorization();
          debugPrint('📱 ATT Status requested: $currentStatus');
        } else {
          debugPrint('📱 ATT Status existing: $currentStatus');
        }

        // Enable Facebook advertiser ID collection only if user authorizes
        if (currentStatus == TrackingStatus.authorized) {
          await _facebook.setAdvertiserTracking(enabled: true);
        } else {
          await _facebook.setAdvertiserTracking(enabled: false);
        }
      }

      // 2. Enable Facebook SDK auto-logging
      await _facebook.setAutoLogAppEventsEnabled(true);

      _initialized = true;
      debugPrint('✅ TrackingService: Initialized (Facebook + Firebase)');
    } catch (e) {
      debugPrint('⚠️ TrackingService Error during init: $e');
    }
  }

  // ─── Core Auth Events (Standard Facebook Events) ─────────────────────────

  /// Log when a user completes registration — used for conversion campaigns.
  Future<void> logRegistration({String method = 'email'}) async {
    await _logBoth(
      facebookEvent: () => _facebook.logCompletedRegistration(
        registrationMethod: method,
      ),
      firebaseEvent: 'sign_up',
      firebaseParams: {'method': method},
    );
  }

  /// Log when a user logs in — used for retargeting active users.
  Future<void> logLogin({String method = 'email'}) async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(
        name: 'fb_mobile_login',
        parameters: {'fb_registration_method': method},
      ),
      firebaseEvent: 'login',
      firebaseParams: {'method': method},
    );
  }

  // ─── Content / Screen Events ─────────────────────────────────────────────

  /// Log a screen view — useful for understanding user navigation flow.
  Future<void> logScreenView(String screenName) async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(
        name: 'fb_mobile_content_view',
        parameters: {'fb_content_type': 'screen', 'fb_content_id': screenName},
      ),
      firebaseEvent: 'screen_view',
      firebaseParams: {'screen_name': screenName},
    );
  }

  /// Log when user views a vehicle/fleet listing.
  Future<void> logViewVehicle(String vehicleType, {String? vehicleId}) async {
    await _logBoth(
      facebookEvent: () => _facebook.logViewContent(
        type: 'vehicle',
        id: vehicleId ?? vehicleType,
        currency: 'INR',
      ),
      firebaseEvent: 'view_item',
      firebaseParams: {
        'item_category': 'vehicle',
        'item_id': vehicleId ?? vehicleType,
        'item_name': vehicleType,
      },
    );
  }

  // ─── KYC / Onboarding Events ──────────────────────────────────────────────

  /// Log when KYC submission begins — tracks onboarding completion rate.
  Future<void> logKYCStarted() async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(name: 'kyc_started'),
      firebaseEvent: 'kyc_started',
    );
  }

  /// Log when KYC is submitted successfully.
  Future<void> logKYCSubmitted() async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(name: 'kyc_submitted'),
      firebaseEvent: 'kyc_submitted',
    );
  }

  /// Log when KYC is verified — high-value event for lookalike audiences.
  Future<void> logKYCVerified() async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(name: 'kyc_verified'),
      firebaseEvent: 'kyc_verified',
    );
  }

  // ─── Vehicle / Fleet Events ───────────────────────────────────────────────

  /// Log when a vehicle is added — measures vendor engagement.
  Future<void> logVehicleAdded(String vehicleType) async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(
        name: 'vehicle_added',
        parameters: {'vehicle_type': vehicleType},
      ),
      firebaseEvent: 'vehicle_added',
      firebaseParams: {'vehicle_type': vehicleType},
    );
  }

  /// Log when a fleet is assigned to a driver.
  Future<void> logFleetAssigned() async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(name: 'fleet_assigned'),
      firebaseEvent: 'fleet_assigned',
    );
  }

  // ─── Order / Revenue Events ───────────────────────────────────────────────

  /// Log a completed order / delivery — most valuable conversion event.
  /// [amount] is in INR. This feeds Facebook's purchase optimization.
  Future<void> logOrderCompleted({
    required double amount,
    String currency = 'INR',
    String? orderId,
  }) async {
    await _logBoth(
      facebookEvent: () => _facebook.logPurchase(
        amount: amount,
        currency: currency,
        parameters: orderId != null ? {'order_id': orderId} : null,
      ),
      firebaseEvent: 'purchase',
      firebaseParams: {
        'value': amount,
        'currency': currency,
        if (orderId != null) 'transaction_id': orderId,
      },
    );
  }

  /// Log when a payment is initiated (before confirmed).
  Future<void> logPaymentInitiated(double amount) async {
    await _logBoth(
      facebookEvent: () => _facebook.logInitiatedCheckout(
        totalPrice: amount,
        currency: 'INR',
      ),
      firebaseEvent: 'begin_checkout',
      firebaseParams: {'value': amount, 'currency': 'INR'},
    );
  }

  // ─── Support / Engagement Events ─────────────────────────────────────────

  /// Log when user contacts support — indicates friction in the flow.
  Future<void> logSupportContacted(String method) async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(
        name: 'support_contacted',
        parameters: {'method': method},
      ),
      firebaseEvent: 'support_contacted',
      firebaseParams: {'method': method},
    );
  }

  /// Log when user views a notification.
  Future<void> logNotificationViewed() async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(name: 'notification_viewed'),
      firebaseEvent: 'notification_viewed',
    );
  }

  // ─── Generic / Custom Events ──────────────────────────────────────────────

  /// Log any custom event by name. Used for A/B testing and experiments.
  Future<void> logEvent(
    String eventName, {
    Map<String, Object>? parameters,
  }) async {
    await _logBoth(
      facebookEvent: () => _facebook.logEvent(
        name: eventName,
        parameters: parameters,
      ),
      firebaseEvent: eventName,
      firebaseParams: parameters,
    );
  }

  // ─── User Identity (for better ad targeting) ─────────────────────────────

  /// Set user ID for cross-device tracking. Call after login.
  Future<void> setUserId(String userId) async {
    try {
      await _firebase.setUserId(id: userId);
      if (!kIsWeb) {
        await _facebook.setUserID(userId);
      }
      debugPrint('📊 Tracking: User ID set');
    } catch (e) {
      debugPrint('⚠️ Tracking setUserId error: $e');
    }
  }

  /// Set user properties for audience segmentation.
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
        await _facebook.logEvent(
          name: 'user_property_update',
          parameters: {'kyc_status': kycStatus},
        );
      }
      if (vehicleCount != null) {
        await _firebase.setUserProperty(name: 'vehicle_count', value: vehicleCount);
      }
    } catch (e) {
      debugPrint('⚠️ Tracking setUserProperties error: $e');
    }
  }

  // ─── Private Helper ───────────────────────────────────────────────────────

  Future<void> _logBoth({
    required Future<void> Function() facebookEvent,
    required String firebaseEvent,
    Map<String, Object>? firebaseParams,
  }) async {
    if (!_initialized) {
      debugPrint('⚠️ TrackingService not initialized — call initialize() first');
    }
    // Facebook SDK is not supported on web
    if (!kIsWeb) {
      try {
        await facebookEvent();
      } catch (e) {
        debugPrint('⚠️ Facebook tracking error ($firebaseEvent): $e');
      }
    }
    try {
      await _firebase.logEvent(name: firebaseEvent, parameters: firebaseParams);
    } catch (e) {
      debugPrint('⚠️ Firebase tracking error ($firebaseEvent): $e');
    }
    debugPrint('📊 Tracked: $firebaseEvent');
  }
}

// Global singleton for easy access throughout the app
final trackingService = TrackingService();
