import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/permission_service.dart';
import '../services/vendor_fcm_service.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {

  // Logo bounce in
  late AnimationController _logoCtrl;
  late Animation<double> _logoScale;

  // Pulse ring (repeating)
  late AnimationController _pulseCtrl;
  late Animation<double> _pulseScale;
  late Animation<double> _pulseOpacity;

  // Text fade + slide up
  late AnimationController _textCtrl;
  late Animation<double> _textFade;
  late Animation<Offset> _textSlide;

  @override
  void initState() {
    super.initState();

    // Logo bounces in
    _logoCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _logoScale = Tween<double>(begin: 0.3, end: 1.0)
        .animate(CurvedAnimation(parent: _logoCtrl, curve: Curves.elasticOut));

    // Pulse ring keeps going
    _pulseCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1500))
      ..repeat();
    _pulseScale   = Tween<double>(begin: 1.0, end: 2.2)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeOut));
    _pulseOpacity = Tween<double>(begin: 0.6, end: 0.0)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeOut));

    // Text slides up after logo
    _textCtrl  = AnimationController(vsync: this, duration: const Duration(milliseconds: 500));
    _textFade  = Tween<double>(begin: 0.0, end: 1.0)
        .animate(CurvedAnimation(parent: _textCtrl, curve: Curves.easeIn));
    _textSlide = Tween<Offset>(begin: const Offset(0, 0.6), end: Offset.zero)
        .animate(CurvedAnimation(parent: _textCtrl, curve: Curves.easeOut));

    // Start sequence
    _logoCtrl.forward().then((_) => _textCtrl.forward());

    _requestPermissionsAndCheckAuth();
  }

  @override
  void dispose() {
    _logoCtrl.dispose();
    _pulseCtrl.dispose();
    _textCtrl.dispose();
    super.dispose();
  }

  Future<void> _requestPermissionsAndCheckAuth() async {
    // Only ask for notifications at startup — camera/storage/location
    // are requested contextually when the user actually needs them.
    // (Asking all permissions upfront violates App Store & Play Store policy)
    await Future.delayed(const Duration(milliseconds: 1000));
    if (mounted) {
      await PermissionService.requestNotification(context);
    }
    // Refresh FCM token and save to server every app open
    _refreshFcmToken();
    await _checkAuth();
  }

  Future<void> _refreshFcmToken() async {
    // Validate token length before saving — mirrors customer app's token quality check.
    // Full re-initialization (delete + fresh token) happens after login via VendorFcmService.
    await VendorFcmService.refreshOnSplash();
  }

  Future<void> _checkAuth() async {
    await Future.delayed(const Duration(milliseconds: 1800));
    if (!mounted) return;

    final prefs             = await SharedPreferences.getInstance();
    final hasSeenOnboarding = prefs.getBool('has_seen_onboarding') ?? false;
    final selectedRole      = prefs.getString('selected_role');
    final driverDataJson    = prefs.getString('driver_session');
    final user              = FirebaseAuth.instance.currentUser;

    if (!hasSeenOnboarding)                              { context.go('/onboarding');      return; }
    if (selectedRole == 'driver' && driverDataJson != null) { context.go('/driver-dashboard'); return; }
    if (user != null)                                    { context.go('/vendor');           return; }
    if      (selectedRole == 'vendor') context.go('/login');
    else if (selectedRole == 'driver') context.go('/driver-login');
    else                               context.go('/role-selection');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0D2E6E),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Spacer(),

            // ── Logo + pulse ───────────────────────────────────────
            SizedBox(
              width: 200,
              height: 200,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // Pulse ring — always behind logo
                  AnimatedBuilder(
                    animation: _pulseCtrl,
                    builder: (_, __) => Transform.scale(
                      scale: _pulseScale.value,
                      child: Opacity(
                        opacity: _pulseOpacity.value,
                        child: Container(
                          width: 118,
                          height: 118,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                        ),
                      ),
                    ),
                  ),

                  // Logo — always visible, only scale bounces
                  ScaleTransition(
                    scale: _logoScale,
                    child: Container(
                        width: 118,
                        height: 118,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(28),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.3),
                              blurRadius: 28,
                              offset: const Offset(0, 10),
                            ),
                          ],
                        ),
                        child: Image.asset(
                          'assets/images/app_icon.png',
                          fit: BoxFit.contain,
                        ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),

            // ── Text slides up ─────────────────────────────────────
            SlideTransition(
              position: _textSlide,
              child: FadeTransition(
                opacity: _textFade,
                child: Column(
                  children: [
                    const Text(
                      'TRUCK UNION',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                        letterSpacing: 4,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.white30),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'Global Logistics Platform',
                        style: TextStyle(fontSize: 13, color: Colors.white70, letterSpacing: 1),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const Spacer(),

            // ── Loading dots ───────────────────────────────────────
            const _LoadingDots(),
            const SizedBox(height: 44),
          ],
        ),
      ),
    );
  }
}

// ── Animated loading dots ─────────────────────────────────────────────────────
class _LoadingDots extends StatefulWidget {
  const _LoadingDots();
  @override
  State<_LoadingDots> createState() => _LoadingDotsState();
}

class _LoadingDotsState extends State<_LoadingDots>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat();
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _ctrl,
      builder: (_, __) => Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(3, (i) {
          final t = ((_ctrl.value - i / 3) % 1.0).clamp(0.0, 1.0);
          final opacity = (t < 0.5 ? t * 2 : (1.0 - t) * 2).clamp(0.2, 1.0);
          return Container(
            margin: const EdgeInsets.symmetric(horizontal: 5),
            width: 7,
            height: 7,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withOpacity(opacity),
            ),
          );
        }),
      ),
    );
  }
}
