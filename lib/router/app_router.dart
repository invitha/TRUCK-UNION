import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../screens/splash_screen.dart';
import '../screens/role_selection_screen.dart';
import '../screens/onboarding_screen.dart';
import '../screens/login_screen.dart';
import '../screens/signup_screen.dart';
import '../screens/driver_login_screen.dart';
import '../screens/vendor/vendor_home_shell.dart';
import '../screens/vendor/vendor_dashboard.dart';
import '../screens/vendor/vendor_support_screen.dart';
import '../screens/vendor/my_vehicles_screen.dart';
import '../screens/vendor/vendor_chat_screen.dart';
import '../screens/vendor/vendor_profile_screen.dart';
import '../screens/vendor/edit_profile_screen.dart';
import '../screens/vendor/notifications_screen.dart';
import '../screens/vendor/delete_account_screen.dart';
import '../screens/vendor/help_support_screen.dart';
import '../screens/vendor/kyc_verification_screen.dart';
import '../screens/vendor/add_vehicle_screen.dart';
import '../screens/vendor/assigned_fleets_screen.dart';
import '../screens/vendor/vendor_tour_screen.dart';
import '../screens/driver/driver_dashboard.dart';
import '../screens/driver/driver_tour_screen.dart';

final GlobalKey<NavigatorState> rootNavigatorKey = GlobalKey<NavigatorState>();

final GoRouter appRouter = GoRouter(
  navigatorKey: rootNavigatorKey,
  initialLocation: '/',
  redirect: (context, state) async {
    final user              = FirebaseAuth.instance.currentUser;
    final isVendorLoggedIn  = user != null;
    final path              = state.uri.path;

    final prefs             = await SharedPreferences.getInstance();
    final hasSeenOnboarding = prefs.getBool('has_seen_onboarding') ?? false;
    final driverSession     = prefs.getString('driver_session');
    final isDriverLoggedIn  = driverSession != null;

    // ── Protect vendor routes ──────────────────────────────────────────────
    if (path.startsWith('/vendor') && !isVendorLoggedIn) {
      return '/login';
    }

    // ── Protect driver dashboard ───────────────────────────────────────────
    if (path == '/driver-dashboard' && !isDriverLoggedIn) {
      return '/driver-login';
    }

    // ── Redirect logged-in users away from auth screens ───────────────────
    if (isVendorLoggedIn && (path == '/login' || path == '/signup')) {
      return '/vendor';
    }
    if (isDriverLoggedIn && path == '/driver-login') {
      return '/driver-dashboard';
    }

    // ── Onboarding is handled by SplashScreen directly ───────────────────
    // (removed from here so splash always shows first)

    return null;
  },
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const SplashScreen(),
    ),
    GoRoute(
      path: '/role-selection',
      builder: (context, state) => const RoleSelectionScreen(),
    ),
    GoRoute(
      path: '/onboarding',
      builder: (context, state) => const OnboardingScreen(),
    ),
    GoRoute(
      path: '/login',
      builder: (context, state) => const LoginScreen(),
    ),
    GoRoute(
      path: '/signup',
      builder: (context, state) => const SignupScreen(),
    ),
    GoRoute(
      path: '/driver-login',
      builder: (context, state) => const DriverLoginScreen(),
    ),

    // ── Driver dashboard — loads session from SharedPreferences ───────────
    GoRoute(
      path: '/driver-dashboard',
      builder: (context, state) => const _DriverDashboardLoader(),
    ),

    // ── Vendor routes with bottom navigation ─────────────────────────────
    ShellRoute(
      builder: (context, state, child) => VendorHomeShell(child: child),
      routes: [
        GoRoute(
          path: '/vendor',
          pageBuilder: (context, state) => const NoTransitionPage(child: VendorDashboard()),
        ),
        GoRoute(
          path: '/vendor/vehicles',
          pageBuilder: (context, state) => const NoTransitionPage(child: MyVehiclesScreen()),
        ),
        GoRoute(
          path: '/vendor/assigned-fleets',
          pageBuilder: (context, state) => const NoTransitionPage(child: AssignedFleetsScreen()),
        ),
        GoRoute(
          path: '/vendor/chat',
          pageBuilder: (context, state) => const NoTransitionPage(child: HelpSupportScreen()),
        ),
        GoRoute(
          path: '/vendor/profile',
          pageBuilder: (context, state) => const NoTransitionPage(child: VendorProfileScreen()),
        ),
      ],
    ),

    // ── Full screen vendor routes (no bottom nav) ─────────────────────────
    GoRoute(path: '/vendor/support',      builder: (_, __) => const VendorSupportScreen()),
    GoRoute(path: '/vendor/help',         builder: (_, __) => const HelpSupportScreen()),
    GoRoute(path: '/vendor/chat-screen',  builder: (_, __) => const VendorChatScreen()),
    GoRoute(path: '/vendor/edit-profile', builder: (_, __) => const EditProfileScreen()),
    GoRoute(path: '/vendor/notifications',builder: (_, __) => const NotificationsScreen()),
    GoRoute(path: '/vendor/delete-account',builder:(_, __) => const DeleteAccountScreen()),
    GoRoute(path: '/vendor/add-vehicle',  builder: (_, __) => const AddVehicleScreen()),
    GoRoute(path: '/vendor/kyc',          builder: (_, __) => const KYCVerificationScreen()),
    GoRoute(path: '/vendor-tour',         builder: (_, __) => const VendorTourScreen()),
    GoRoute(path: '/driver-tour',         builder: (_, __) => const DriverTourScreen()),
    GoRoute(
      path: '/vendor/bulk-upload',
      builder: (_, __) => Scaffold(
        appBar: AppBar(title: const Text('Bulk Upload')),
        body: const Center(child: Text('Bulk Upload - Coming Soon')),
      ),
    ),
  ],
);

// ── Loader widget: reads driver session from prefs and passes to dashboard ──
class _DriverDashboardLoader extends StatefulWidget {
  const _DriverDashboardLoader();

  @override
  State<_DriverDashboardLoader> createState() => _DriverDashboardLoaderState();
}

class _DriverDashboardLoaderState extends State<_DriverDashboardLoader> {
  Map<String, dynamic>? _driverData;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final json  = prefs.getString('driver_session');
    if (json != null && mounted) {
      setState(() => _driverData = jsonDecode(json) as Map<String, dynamic>);
    } else if (mounted) {
      // No session — redirect to login
      context.go('/driver-login');
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_driverData == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return DriverDashboard(driverData: _driverData!);
  }
}
