import 'dart:async' show unawaited;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:math';
import 'dart:convert';
import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'dart:io' show Platform;
import '../config/app_theme.dart';
import '../services/api_service.dart';
import '../services/tracking_service.dart';
import '../services/vendor_fcm_service.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _isLoading = false;
  bool _isGoogleLoading = false;
  bool _isAppleLoading = false;
  bool _obscurePassword = true;
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    );
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.easeIn),
    );
    _animationController.forward();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _animationController.dispose();
    super.dispose();
  }

  Future<void> _signInWithGoogle() async {
    // Check if running on web
    if (kIsWeb) {
      _showError('Google Sign-In is only available on mobile devices. Please use email/password login on web.');
      return;
    }

    setState(() => _isGoogleLoading = true);

    try {
      final GoogleSignIn googleSignIn = GoogleSignIn(
        scopes: ['email', 'profile'],
        // serverClientId is for getting ID token for Firebase Auth (Android + web)
        // On iOS, google_sign_in reads CLIENT_ID from GoogleService-Info.plist automatically
        serverClientId: Platform.isIOS
            ? null
            : '907690538063-rj6tc52deon3u31vdeima26d58innnqo.apps.googleusercontent.com',
      );

      await googleSignIn.signOut();
      
      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      if (googleUser == null) {
        setState(() => _isGoogleLoading = false);
        return;
      }

      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;
      
      if (googleAuth.accessToken == null || googleAuth.idToken == null) {
        throw Exception('Failed to get authentication tokens');
      }
      
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      final userCredential = await FirebaseAuth.instance.signInWithCredential(credential);
      await _handleSuccessfulLogin(userCredential);
    } on PlatformException catch (e) {
      print('PlatformException: ${e.code} - ${e.message}');
      String message = 'Google Sign-In failed';
      
      switch (e.code) {
        case 'sign_in_failed':
          message = 'Google Sign-In configuration error. Please contact support.';
          break;
        case 'network_error':
          message = 'Network error. Please check your internet connection.';
          break;
        case 'sign_in_cancelled':
          message = 'Sign-in was cancelled.';
          break;
        default:
          message = 'Google Sign-In failed: ${e.message ?? e.code}';
      }
      
      _showError(message);
    } catch (e) {
      print('Google Sign-In Error: $e');
      _showError('An unexpected error occurred. Please try again.');
    } finally {
      setState(() => _isGoogleLoading = false);
    }
  }

  Future<void> _signInWithApple() async {
    setState(() => _isAppleLoading = true);
    try {
      // Generate nonce for security
      final rawNonce = _generateNonce();
      final nonce = _sha256ofString(rawNonce);

      final appleCredential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
        nonce: nonce,
      );

      final oauthCredential = OAuthProvider('apple.com').credential(
        idToken: appleCredential.identityToken,
        rawNonce: rawNonce,
        accessToken: appleCredential.authorizationCode,
      );

      final userCredential =
          await FirebaseAuth.instance.signInWithCredential(oauthCredential);

      // Apple only sends name on first sign-in — save it
      final fullName = [
        appleCredential.givenName,
        appleCredential.familyName,
      ].where((e) => e != null && e.isNotEmpty).join(' ');

      if (fullName.isNotEmpty &&
          (userCredential.user?.displayName == null ||
              userCredential.user!.displayName!.isEmpty)) {
        await userCredential.user?.updateDisplayName(fullName);
      }

      await _handleSuccessfulLogin(userCredential);
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code != AuthorizationErrorCode.canceled) {
        _showError('Apple Sign-In failed: ${e.message}');
      }
    } catch (e) {
      _showError('Apple Sign-In failed. Please try again.');
    } finally {
      setState(() => _isAppleLoading = false);
    }
  }

  /// Generates a cryptographically random nonce string
  String _generateNonce([int length = 32]) {
    const charset =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvwxyz-._';
    final random = Random.secure();
    return List.generate(length, (_) => charset[random.nextInt(charset.length)])
        .join();
  }

  /// Returns the SHA-256 hash of a string
  String _sha256ofString(String input) {
    final bytes = utf8.encode(input);
    final digest = sha256.convert(bytes);
    return digest.toString();
  }

  Future<void> _signInWithEmail() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      final userCredential = await FirebaseAuth.instance.signInWithEmailAndPassword(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );
      await _handleSuccessfulLogin(userCredential);
    } catch (e) {
      _showError('Login failed: ${e.toString()}');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _handleSuccessfulLogin(UserCredential userCredential) async {
    try {
      final user = userCredential.user;
      if (user == null) {
        _showError('Login failed: No user data');
        return;
      }

      final firebaseUid = user.uid;
      final userEmail = user.email ?? '';
      final userName = user.displayName ?? '';

      print('🔵 Checking user role for: $userEmail');

      // Check user role from database
      final roleResponse = await ApiService.checkUserRole(
        firebaseUid: firebaseUid,
        email: userEmail,
        name: userName,
      );

      print('🔵 Role response: $roleResponse');

      if (roleResponse['status'] == 'success') {
        final userRole = roleResponse['role'];

        if (userRole == null) {
          // New user with no role assigned yet — register as vendor
          final updateResponse = await ApiService.updateUserRole(
            firebaseUid: firebaseUid,
            email: userEmail,
            name: userName,
            role: 'vendor',
          );
          if (updateResponse['status'] == 'success') {
            final prefs = await SharedPreferences.getInstance();
            await prefs.setString('user_email', userEmail);
            await prefs.setBool('has_used_app', true);
            await prefs.setString('user_type', 'vendor');
            await trackingService.logRegistration(method: 'google');
            await trackingService.setUserId(firebaseUid);
            await trackingService.setUserProperties(userType: 'vendor');
            if (!mounted) return;
            context.go('/vendor');
          } else {
            _showError('Failed to create vendor account. Please try again.');
          }
          return;
        }

        if (userRole == 'vendor') {
          // User is a vendor, allow login
          print('✅ User is a vendor, proceeding to dashboard');
          
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('user_email', userEmail);
          await prefs.setBool('has_used_app', true);
          await prefs.setString('user_type', 'vendor');

          // Track login for Facebook Meta ads
          await trackingService.logLogin(method: 'email');
          await trackingService.setUserId(firebaseUid);
          await trackingService.setUserProperties(userType: 'vendor');

          // Fresh FCM token after login — validates ≥150 chars (mirrors customer app)
          unawaited(VendorFcmService.initializeForVendor(firebaseUid));

          if (!mounted) return;
          context.go('/vendor');
        } else {
          // User has different role - shouldn't happen with separate Firebase
          print('⚠️ Unexpected role: $userRole');
          await FirebaseAuth.instance.signOut();
          _showError('Invalid account. Please sign up as a vendor.');
        }
      } else if (roleResponse['status'] == 'not_found') {
        // New user, create as vendor
        print('🆕 New user, creating vendor account');
        
        final updateResponse = await ApiService.updateUserRole(
          firebaseUid: firebaseUid,
          email: userEmail,
          name: userName,
          role: 'vendor',
        );

        if (updateResponse['status'] == 'success') {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('user_email', userEmail);
          await prefs.setBool('has_used_app', true);
          await prefs.setString('user_type', 'vendor');

          // Track new registration for Facebook Meta ads
          await trackingService.logRegistration(method: 'email');
          await trackingService.setUserId(firebaseUid);
          await trackingService.setUserProperties(userType: 'vendor');

          if (!mounted) return;
          context.go('/vendor');
        } else {
          _showError('Failed to create vendor account. Please try again.');
        }
      } else {
        _showError(roleResponse['message'] ?? 'Failed to verify account');
      }
    } catch (e) {
      print('🔴 Error in login: $e');
      _showError('Login failed: ${e.toString()}');
    }
  }

  Future<void> _showForgotPasswordDialog() async {
    final emailController = TextEditingController(text: _emailController.text.trim());
    final formKey = GlobalKey<FormState>();
    bool isSending = false;

    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Reset Password', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Color(0xFF0D2E6E))),
          insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
          content: SingleChildScrollView(
            child: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Enter your email address and we\'ll send you a link to reset your password.',
                    style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                const SizedBox(height: 16),
                TextFormField(
                  controller: emailController,
                  keyboardType: TextInputType.emailAddress,
                  decoration: _inputDec('Email', Icons.email_outlined),
                  validator: (v) {
                    if (v?.isEmpty ?? true) return 'Email is required';
                    if (!v!.contains('@')) return 'Enter a valid email';
                    return null;
                  },
                ),
              ],
            ),
          ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel', style: TextStyle(color: Color(0xFF64748B))),
            ),
            ElevatedButton(
              onPressed: isSending
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setDialogState(() => isSending = true);
                      try {
                        await FirebaseAuth.instance.sendPasswordResetEmail(
                          email: emailController.text.trim(),
                        );
                        if (ctx.mounted) Navigator.pop(ctx);
                        _showSuccess('Password reset email sent. Check your inbox.');
                      } catch (e) {
                        setDialogState(() => isSending = false);
                        _showError('Failed to send reset email. Check the address and try again.');
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0D2E6E),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: isSending
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Send Link', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _showSuccess(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green.shade600,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: AppTheme.accentRed,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/role-selection');
      },
      child: Scaffold(
        backgroundColor: Colors.white,
        body: Column(
          children: [
            // ── Navy top header ──────────────────────────────────
            Container(
              width: double.infinity,
              color: const Color(0xFF0D2E6E),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
                  child: Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.25),
                              blurRadius: 20,
                              spreadRadius: 2,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: ClipOval(
                          child: Image.asset(
                            'assets/images/app_icon.png',
                            width: 90,
                            height: 90,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      const Text('TRUCK UNION',
                          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 2.5)),
                      const SizedBox(height: 4),
                      const Text('Sign in to your vendor account',
                          style: TextStyle(fontSize: 13, color: Colors.white70)),
                    ],
                  ),
                ),
              ),
            ),

            // ── Form area ────────────────────────────────────────
            Expanded(
              child: FadeTransition(
                opacity: _fadeAnimation,
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                  const SizedBox(height: 24),

                  // Form
                  Form(
                    key: _formKey,
                    child: Column(
                      children: [
                        TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          decoration: _inputDec('Email', Icons.email_outlined),
                          validator: (v) {
                            if (v?.isEmpty ?? true) return 'Email is required';
                            if (!v!.contains('@')) return 'Enter a valid email';
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          decoration: _inputDec('Password', Icons.lock_outline).copyWith(
                            suffixIcon: IconButton(
                              icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility, size: 20, color: const Color(0xFF94A3B8)),
                              onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                            ),
                          ),
                          validator: (v) {
                            if (v?.isEmpty ?? true) return 'Password is required';
                            if (v!.length < 6) return 'Min 6 characters';
                            return null;
                          },
                        ),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton(
                            onPressed: _showForgotPasswordDialog,
                            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 4)),
                            child: const Text('Forgot Password?', style: TextStyle(color: Color(0xFF0D2E6E), fontSize: 12, fontWeight: FontWeight.w600)),
                          ),
                        ),
                        const SizedBox(height: 8),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _signInWithEmail,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF0D2E6E),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              elevation: 0,
                            ),
                            child: _isLoading
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                : const Text('Sign In', style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700)),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // OR divider
                  Row(
                    children: [
                      const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: Text('OR', style: TextStyle(fontSize: 12, color: Colors.grey.shade500, fontWeight: FontWeight.w600)),
                      ),
                      const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // Google button
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: OutlinedButton(
                      onPressed: _isGoogleLoading ? null : _signInWithGoogle,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF3C4043),
                        side: const BorderSide(color: Color(0xFFCBD5E0)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        backgroundColor: Colors.white,
                      ),
                      child: _isGoogleLoading
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                // Google G logo built from coloured arcs
                                SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CustomPaint(painter: _GoogleLogoPainter()),
                                ),
                                const SizedBox(width: 10),
                                const Text('Continue with Google',
                                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF3C4043))),
                              ],
                            ),
                    ),
                  ),

                  if (!kIsWeb && Platform.isIOS) ...[
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: OutlinedButton.icon(
                        onPressed: _isAppleLoading ? null : _signInWithApple,
                        icon: _isAppleLoading
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                            : const Icon(Icons.apple, size: 22, color: Colors.black),
                        label: const Text('Continue with Apple', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.black87)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.black87,
                          side: const BorderSide(color: Color(0xFFCBD5E0)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ],

                  const SizedBox(height: 16),

                  // Sign up row
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text("Don't have an account? ", style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                      GestureDetector(
                        onTap: () => context.push('/signup'),
                        child: const Text('Sign Up', style: TextStyle(fontSize: 13, color: Color(0xFF0D2E6E), fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Back to Role Selection
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: ElevatedButton.icon(
                      onPressed: () => context.go('/role-selection'),
                      icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 13, color: Colors.white),
                      label: const Text('Back to Role Selection',
                          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0D2E6E),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                      ),
                    ),
                  ),

                  const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDec(String label, IconData icon) => InputDecoration(
    labelText: label,
    labelStyle: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
    prefixIcon: Icon(icon, size: 20, color: const Color(0xFF94A3B8)),
    filled: true,
    fillColor: const Color(0xFFF8FAFC),
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF0D2E6E), width: 2)),
    errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Colors.red)),
    focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Colors.red, width: 2)),
  );
}

// ── Google "G" logo painter ───────────────────────────────────────────────────
class _GoogleLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width / 2;
    final cx = r, cy = r;

    // Full circle (light grey base)
    canvas.drawCircle(Offset(cx, cy), r,  Paint()..color = const Color(0xFFEEEEEE));

    // Red top-right arc
    _arc(canvas, cx, cy, r, -60, 150, const Color(0xFFEA4335));
    // Blue top-left arc
    _arc(canvas, cx, cy, r, 210, 60, const Color(0xFF4285F4));
    // Yellow bottom-left arc
    _arc(canvas, cx, cy, r, 150, 60, const Color(0xFFFBBC05));
    // Green bottom-right arc
    _arc(canvas, cx, cy, r, 90, 60, const Color(0xFF34A853));

    // White centre
    canvas.drawCircle(Offset(cx, cy), r * 0.55, Paint()..color = Colors.white);

    // Blue G bar (right notch)
    final barPaint = Paint()..color = const Color(0xFF4285F4);
    canvas.drawRect(Rect.fromLTWH(cx, cy - r * 0.18, r, r * 0.36), barPaint);

    // Re-cover inner circle white
    canvas.drawCircle(Offset(cx, cy), r * 0.55, Paint()..color = Colors.white);
    // Small blue G cut
    canvas.drawRect(Rect.fromLTWH(cx, cy - r * 0.18, r * 0.45, r * 0.36),
        Paint()..color = const Color(0xFF4285F4));
  }

  void _arc(Canvas c, double cx, double cy, double r, double startDeg, double sweepDeg, Color color) {
    const deg = 3.14159265 / 180;
    c.drawArc(
      Rect.fromCircle(center: Offset(cx, cy), radius: r),
      startDeg * deg,
      sweepDeg * deg,
      true,
      Paint()..color = color,
    );
  }

  @override
  bool shouldRepaint(_) => false;
}
