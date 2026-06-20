import 'package:flutter/material.dart';
import 'dart:async';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tutorial_coach_mark/tutorial_coach_mark.dart';
import 'package:firebase_auth/firebase_auth.dart';
import '../../services/api_service.dart';
import '../../config/app_theme.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'driver_home_dashboard.dart';
import 'driver_support_screen.dart';
import 'driver_orders_screen.dart';
import 'driver_profile_screen.dart';
import 'driver_notifications_screen.dart';

class DriverDashboard extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverDashboard({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverDashboard> createState() => _DriverDashboardState();
}

class _DriverDashboardState extends State<DriverDashboard> {
  Timer? _locationTimer;
  Timer? _notifTimer;
  Position? _currentPosition;
  String? _currentAddress;
  int _selectedIndex = 0;
  int _unreadCount = 0;

  // ── GlobalKeys for coach mark tour ─────────────────────────────────────────
  final GlobalKey _homeTabKey    = GlobalKey(debugLabel: 'driver_tab_home');
  final GlobalKey _ordersTabKey  = GlobalKey(debugLabel: 'driver_tab_orders');
  final GlobalKey _supportTabKey = GlobalKey(debugLabel: 'driver_tab_support');
  final GlobalKey _profileTabKey = GlobalKey(debugLabel: 'driver_tab_profile');

  @override
  void initState() {
    super.initState();
    _initLocationTracking();
    _loadUnreadCount();
    _notifTimer = Timer.periodic(const Duration(seconds: 30), (_) => _loadUnreadCount());
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkAndShowTour());
  }

  Future<void> _loadUnreadCount() async {
    try {
      final vehicleId = widget.driverData['driver']?['vehicle_id']?.toString() ?? '';
      final uid = vehicleId.isNotEmpty ? 'driver_$vehicleId' : '';
      if (uid.isEmpty) return;
      final res = await ApiService.getNotifications(
        firebaseUid: uid,
        timestamp: DateTime.now().millisecondsSinceEpoch ~/ 1000,
      );
      if (mounted) {
        setState(() {
          _unreadCount = (res['unread_count'] as int?) ?? 0;
        });
      }
    } catch (_) {}
  }

  Future<void> _checkAndShowTour() async {
    final prefs = await SharedPreferences.getInstance();
    final shown = prefs.getBool('driver_tour_shown') ?? false;
    if (!shown && mounted) {
      await Future.delayed(const Duration(milliseconds: 900));
      if (mounted) _launchTour();
    }
  }

  void _launchTour() {
    final targets = <TargetFocus>[
      TargetFocus(
        identify: 'driver_tab_home',
        keyTarget: _homeTabKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _DriverTourCard(
              step: '1 / 4',
              icon: Icons.dashboard_rounded,
              title: 'Home — Your Dashboard',
              body: 'See your truck status and today\'s orders here.\nYour location is tracked automatically.',
            ),
          ),
        ],
      ),
      TargetFocus(
        identify: 'driver_tab_orders',
        keyTarget: _ordersTabKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _DriverTourCard(
              step: '2 / 4',
              icon: Icons.list_alt_rounded,
              title: 'My Orders — Your Deliveries',
              body: 'See all orders assigned to you.\nTap any order to see pickup & delivery address.\nEnter OTP at pickup and delivery to confirm.',
            ),
          ),
        ],
      ),
      TargetFocus(
        identify: 'driver_tab_support',
        keyTarget: _supportTabKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _DriverTourCard(
              step: '3 / 4',
              icon: Icons.headset_mic_rounded,
              title: 'Support — We Are Here!',
              body: 'Any problem with the order or app?\nTap here — our team will help you fast.',
            ),
          ),
        ],
      ),
      TargetFocus(
        identify: 'driver_tab_profile',
        keyTarget: _profileTabKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _DriverTourCard(
              step: '4 / 4',
              icon: Icons.person_rounded,
              title: 'Profile — Complete Your KYC',
              body: 'Upload your license and documents here.\nMore documents = more orders assigned to you!\nGood Luck Driver! 🚛💨',
              isLast: true,
            ),
          ),
        ],
      ),
    ];

    TutorialCoachMark(
      targets: targets,
      colorShadow: const Color(0xFF0D2E6E),
      opacityShadow: 0.92,
      textSkip: 'SKIP TOUR',
      textStyleSkip: const TextStyle(
        color: Colors.white,
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
      paddingFocus: 8,
      onFinish: _markTourDone,
      onSkip: () { _markTourDone(); return true; },
    ).show(context: context);
  }

  Future<void> _markTourDone() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('driver_tour_shown', true);
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    _notifTimer?.cancel();
    _setDriverOffline();
    super.dispose();
  }

  // Initialize location tracking
  Future<void> _initLocationTracking() async {
    try {
      // Check if location services are enabled
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        print('Location services are disabled');
        return;
      }

      // Check location permissions
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          print('Location permissions are denied');
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        print('Location permissions are permanently denied');
        return;
      }

      // Get initial location and send to server
      await _updateLocation();

      // Start periodic location updates every 30 seconds
      _locationTimer = Timer.periodic(const Duration(seconds: 30), (timer) {
        if (mounted) {
          _updateLocation();
        }
      });
    } catch (e) {
      print('Error initializing location tracking: $e');
    }
  }

  // Update current location and send to server
  Future<void> _updateLocation() async {
    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      setState(() {
        _currentPosition = position;
      });

      // Get address from coordinates
      String address = 'GPS: ${position.latitude.toStringAsFixed(6)}, ${position.longitude.toStringAsFixed(6)}';
      try {
        List<Placemark> placemarks = await placemarkFromCoordinates(
          position.latitude,
          position.longitude,
        );
        if (placemarks.isNotEmpty) {
          Placemark place = placemarks[0];
          address = [
            place.street,
            place.locality,
            place.administrativeArea,
          ].where((s) => s != null && s.isNotEmpty).join(', ');
        }
      } catch (_) {}

      if (mounted) {
        setState(() {
          _currentAddress = address;
        });
      }

      // Send location to server
      try {
        final vid = widget.driverData['driver']?['vehicle_id'];
        if (vid != null) {
          await ApiService.updateDriverLocation(
            vehicleId: vid is int ? vid : int.parse(vid.toString()),
            latitude: position.latitude,
            longitude: position.longitude,
            address: address,
          );
        }
      } catch (e) {
        print('Error sending location to server: $e');
      }
    } catch (e) {
      print('Error getting location: $e');
    }
  }

  Future<void> _setDriverOffline() async {
    try {
      final vid = widget.driverData['driver']?['vehicle_id'];
      if (vid != null) {
        await ApiService.setDriverOffline(
          vehicleId: vid is int ? vid : int.parse(vid.toString()),
        );
      }
    } catch (_) {}
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  Future<void> _logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    if (mounted) {
      context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      DriverHomeDashboard(
        driverData: widget.driverData,
      ),
      DriverOrdersScreen(driverData: widget.driverData),
      DriverSupportScreen(driverData: widget.driverData),
      DriverProfileScreen(driverData: widget.driverData),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: IndexedStack(
        index: _selectedIndex,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 20,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(0, Icons.dashboard_rounded, Icons.dashboard_outlined, 'Home', _homeTabKey),
                _buildNavItem(1, Icons.list_alt_rounded, Icons.list_alt_outlined, 'Orders', _ordersTabKey),
                _buildNavItem(2, Icons.headset_mic_rounded, Icons.headset_mic_outlined, 'Support', _supportTabKey),
                _buildNavItem(3, Icons.person_rounded, Icons.person_outlined, 'Profile', _profileTabKey),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData activeIcon, IconData inactiveIcon, String label, GlobalKey key) {
    final isSelected = _selectedIndex == index;
    return GestureDetector(
      key: key,
      onTap: () => _onItemTapped(index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF0D2E6E).withOpacity(0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isSelected ? activeIcon : inactiveIcon,
              color: isSelected ? const Color(0xFF0D2E6E) : Colors.grey[500],
              size: 24,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w400,
                color: isSelected ? const Color(0xFF0D2E6E) : Colors.grey[500],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Tour card widget ──────────────────────────────────────────────────────────

class _DriverTourCard extends StatelessWidget {
  final String step;
  final IconData icon;
  final String title;
  final String body;
  final bool isLast;

  const _DriverTourCard({
    required this.step,
    required this.icon,
    required this.title,
    required this.body,
    this.isLast = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.15),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFF0D2E6E).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: const Color(0xFF0D2E6E), size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      step,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[500],
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0D2E6E),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            body,
            style: TextStyle(
              fontSize: 13,
              color: Colors.grey[700],
              height: 1.5,
            ),
          ),
          if (isLast) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                '✅ You\'re all set! Tap anywhere to start.',
                     style: const TextStyle(
                  fontSize: 12,
                  color: Colors.green,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

