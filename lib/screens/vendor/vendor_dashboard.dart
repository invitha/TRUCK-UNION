import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:go_router/go_router.dart';
import 'dart:async';
import '../../config/app_theme.dart';
import '../../services/api_service.dart';

class VendorDashboard extends StatefulWidget {
  const VendorDashboard({super.key});

  @override
  State<VendorDashboard> createState() => _VendorDashboardState();
}

class _VendorDashboardState extends State<VendorDashboard> {
  late PageController _pageController;
  int _currentSlide = 0;
  String _kycStatus = 'not_submitted'; // not_submitted, submitted, verified, rejected
  String _userName = 'Vendor';
  int _unreadNotifications = 0;
  Timer? _autoSlideTimer;
  Timer? _notificationRefreshTimer;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(initialPage: 0);
    _loadUserName();
    _loadUnreadNotifications();
    _loadKYCStatus();
    _startAutoSlide();
    _startNotificationRefresh();
  }

  Future<void> _loadKYCStatus() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;

    try {
      final response = await ApiService.getKYCStatus(firebaseUid: user.uid);
      if (response['status'] == 'success' && mounted) {
        setState(() {
          _kycStatus = response['kyc_status'] ?? 'not_submitted';
        });
        print('🔵 DASHBOARD: KYC Status loaded: $_kycStatus');
      }
    } catch (e) {
      print('🔴 DASHBOARD: Error loading KYC status: $e');
    }
  }

  void _startNotificationRefresh() {
    // Refresh notification count every 30 seconds
    _notificationRefreshTimer = Timer.periodic(const Duration(seconds: 30), (timer) {
      _loadUnreadNotifications();
    });
  }

  Future<void> _loadUnreadNotifications() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;

    try {
      print('🔵 DASHBOARD: Loading unread notifications for ${user.uid}');
      final response = await ApiService.getNotifications(
        firebaseUid: user.uid,
        timestamp: DateTime.now().millisecondsSinceEpoch,
      );
      
      print('🟢 DASHBOARD: API Response - unread_count: ${response['unread_count']}');
      
      if (response['status'] == 'success' && mounted) {
        final newCount = (response['unread_count'] as int?) ?? 0;
        if (newCount != _unreadNotifications) {
          print('🔔 DASHBOARD: Updating badge from $_unreadNotifications to $newCount');
          setState(() {
            _unreadNotifications = newCount;
          });
        }
      }
    } catch (e) {
      print('🔴 DASHBOARD: Error loading unread notifications: $e');
    }
  }

  void _startAutoSlide() {
    _autoSlideTimer = Timer.periodic(const Duration(seconds: 3), (timer) {
      if (_pageController.hasClients) {
        int nextPage = (_currentSlide + 1) % 3;
        _pageController.animateToPage(
          nextPage,
          duration: const Duration(milliseconds: 400),
          curve: Curves.easeInOut,
        );
      }
    });
  }

  void _loadUserName() {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      String name = user.displayName ?? '';
      if (name.isEmpty) {
        name = user.email?.split('@').first ?? 'Vendor';
      }
      setState(() {
        _userName = name;
      });
      print('🔵 User name loaded: $_userName');
    } else {
      print('🔴 No user logged in');
      setState(() {
        _userName = 'Vendor';
      });
    }
  }

  @override
  void dispose() {
    _autoSlideTimer?.cancel();
    _notificationRefreshTimer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header with notification only
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Hello, $_userName 👋',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Welcome to TRUCK UNION',
                          style: TextStyle(
                            fontSize: 13,
                            color: AppTheme.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                    GestureDetector(
                      onTap: () async {
                        await context.push('/vendor/notifications');
                        // Reload unread count after returning
                        _loadUnreadNotifications();
                      },
                      child: Stack(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppTheme.borderColor),
                            ),
                            child: const Icon(
                              Icons.notifications_outlined,
                              color: AppTheme.primaryBlue,
                              size: 24,
                            ),
                          ),
                          if (_unreadNotifications > 0)
                            Positioned(
                              right: 0,
                              top: 0,
                              child: Container(
                                padding: const EdgeInsets.all(4),
                                decoration: const BoxDecoration(
                                  color: Colors.red,
                                  shape: BoxShape.circle,
                                ),
                                constraints: const BoxConstraints(
                                  minWidth: 18,
                                  minHeight: 18,
                                ),
                                child: Text(
                                  _unreadNotifications > 9 ? '9+' : '$_unreadNotifications',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                  ),
                                  textAlign: TextAlign.center,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Carousel Slides
              SizedBox(
                height: 180,
                child: PageView(
                  controller: _pageController,
                  onPageChanged: (index) {
                    if (mounted) {
                      setState(() => _currentSlide = index);
                    }
                  },
                  children: [
                    _buildImageSlide('assets/images/fleet.png'),
                    _buildImageSlide('assets/images/orders.png'),
                    _buildImageSlide('assets/images/kyc.png'),
                  ],
                ),
              ),

              // Slide Indicators
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    3,
                    (index) => Container(
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      width: _currentSlide == index ? 24 : 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: _currentSlide == index
                            ? AppTheme.primaryBlue
                            : AppTheme.primaryBlue.withOpacity(0.3),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 24),

              // Quick Actions - Horizontal Line (Bigger, No Gaps)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _buildQuickActionIcon(
                      icon: Icons.add_circle_outline_rounded,
                      label: 'Add Vehicle',
                      onTap: () => context.go('/vendor/vehicles'),
                    ),
                    _buildQuickActionIcon(
                      icon: Icons.directions_bus_outlined,
                      label: 'My Fleet',
                      onTap: () => context.go('/vendor/vehicles'),
                    ),
                    _buildQuickActionIcon(
                      icon: Icons.assignment_rounded,
                      label: 'Assigned',
                      onTap: () => context.go('/vendor/assigned-fleets'),
                    ),
                    _buildQuickActionIcon(
                      icon: Icons.support_agent_rounded,
                      label: 'Support',
                      onTap: () => context.go('/vendor/chat'),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              // Why Vendors Choose Us Section
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Why Vendors Choose Us',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0D2E6E),
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildBenefitCard(
                      icon: Icons.trending_up_rounded,
                      title: 'Increase Revenue',
                      description: 'Get consistent orders and grow your business with us',
                      color: const Color(0xFF0D2E6E),
                    ),
                    const SizedBox(height: 12),
                    _buildBenefitCard(
                      icon: Icons.security_rounded,
                      title: 'Secure Payments',
                      description: 'Fast and reliable payment processing every time',
                      color: const Color(0xFF0D2E6E),
                    ),
                    const SizedBox(height: 12),
                    _buildBenefitCard(
                      icon: Icons.support_agent_rounded,
                      title: '24/7 Support',
                      description: 'Dedicated support team always ready to help',
                      color: const Color(0xFF0D2E6E),
                    ),
                    const SizedBox(height: 12),
                    _buildBenefitCard(
                      icon: Icons.analytics_rounded,
                      title: 'Real-time Analytics',
                      description: 'Track your performance with detailed insights',
                      color: const Color(0xFF0D2E6E),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildImageSlide(String assetPath) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.15),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Image.asset(
          assetPath,
          fit: BoxFit.fill,
          width: double.infinity,
          height: 180,
        ),
      ),
    );
  }

  Widget _buildSlide({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color, color.withOpacity(0.8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.3),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Colors.white, size: 36),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.white.withOpacity(0.9),
                  height: 1.3,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQuickActionIcon({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF0D2E6E), Color(0xFF1E88E5)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF0D2E6E).withOpacity(0.3),
                  blurRadius: 12,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white, size: 32),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppTheme.textPrimary,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildBenefitCard({
    required IconData icon,
    required String title,
    required String description,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            const Color(0xFF0D2E6E),
            const Color(0xFF1E40AF),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0D2E6E).withOpacity(0.2),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.15),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: Colors.white, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.white.withOpacity(0.9),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showKYCRequiredDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFFFC107),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(
                Icons.lock_rounded,
                color: Colors.white,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'KYC Required',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
        content: const Text(
          'Please complete KYC verification first to add vehicles.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              context.go('/vendor/kyc');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primaryBlue,
            ),
            child: const Text(
              'Start KYC',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }
}
