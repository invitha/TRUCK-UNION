import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'dart:async';
import '../../config/app_theme.dart';
import '../../services/api_service.dart';
import 'driver_orders_screen.dart';
import 'driver_kyc_screen.dart';
import 'driver_support_screen.dart';
import 'driver_profile_screen.dart';
import 'driver_notifications_screen.dart';

class DriverHomeDashboard extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverHomeDashboard({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverHomeDashboard> createState() => _DriverHomeDashboardState();
}

class _DriverHomeDashboardState extends State<DriverHomeDashboard> {
  late PageController _pageController;
  int _currentSlide = 0;
  Timer? _autoSlideTimer;
  String _kycStatus = 'Pending';
  String? _rejectionReason;
  int _unreadNotifications = 0;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(initialPage: 0);
    _startAutoSlide();
    _fetchKycStatus();
    _fetchNotificationsCount();
  }

  Future<void> _fetchNotificationsCount() async {
    try {
      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
      final driverIdStr = driverIdObj?.toString() ?? '';
      
      if (driverIdStr.isNotEmpty) {
        final driverKycUid = 'driver_$driverIdStr';
        final result = await ApiService.getNotifications(
          firebaseUid: driverKycUid,
          timestamp: DateTime.now().millisecondsSinceEpoch ~/ 1000,
        );
        if (result['status'] == 'success' && mounted) {
          final List notifications = result['data'] ?? result['notifications'] ?? [];
          final unread = notifications.where((n) {
            final isRead = n['is_read'] == true || n['is_read'] == 1 || n['is_read'] == '1';
            return !isRead;
          }).length;
          setState(() {
            _unreadNotifications = unread;
          });
        }
      }
    } catch (e) {
      print('Error fetching notifications count: $e');
    }
  }

  Future<void> _fetchKycStatus() async {
    try {
      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
      final driverId = driverIdObj?.toString() ?? '';
      
      if (driverId.isNotEmpty) {
        final driverKycUid = 'driver_$driverId';
        final response = await ApiService.getDriverKYCStatus(firebaseUid: driverKycUid);
        if (response['status'] == 'success' && mounted) {
          setState(() {
            // Check if KYC data exists and extract status
            if (response['kyc_exists'] == true && response['kyc_data'] != null) {
              _kycStatus = (response['kyc_data']['kyc_status'] ?? 'pending').toString().toLowerCase();
              _rejectionReason = response['kyc_data']['rejection_reason'];
            } else {
              _kycStatus = 'not_submitted';
              _rejectionReason = null;
            }
          });
        }
      }
    } catch (e) {
      print('Error fetching KYC status: $e');
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

  @override
  void dispose() {
    _autoSlideTimer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  String _getDriverName(Map<String, dynamic>? driver) {
    if (driver == null) return 'Driver';
    
    final List<String> possibleNames = [
      driver['driver_name']?.toString() ?? '',
      driver['driverName']?.toString() ?? '',
      driver['driver_username']?.toString() ?? '',
      driver['vendor_driver_name']?.toString() ?? '',
      driver['name']?.toString() ?? '',
      driver['driver_mobile']?.toString() ?? '',
    ];

    for (String name in possibleNames) {
      if (name.isNotEmpty && name.toLowerCase() != 'driver') {
        return name;
      }
    }
    
    for (String name in possibleNames) {
      if (name.isNotEmpty) return name;
    }
    
    return 'Driver';
  }

  @override
  Widget build(BuildContext context) {
    final driver = widget.driverData['driver'];
    
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      appBar: AppBar(
        title: Text(
          'Hello ${_getDriverName(driver)} 👋',
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            color: AppTheme.primaryBlue,
            letterSpacing: -0.5,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppTheme.primaryBlue),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            decoration: BoxDecoration(
              color: AppTheme.primaryBlue.withOpacity(0.05),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.primaryBlue.withOpacity(0.1)),
            ),
              child: Badge(
                isLabelVisible: _unreadNotifications > 0,
                label: Text(
                  _unreadNotifications > 9 ? '9+' : _unreadNotifications.toString(),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                backgroundColor: Colors.red,
                offset: const Offset(4, -4),
                child: IconButton(
                  icon: const Icon(Icons.notifications_none_rounded, color: AppTheme.primaryBlue),
                  onPressed: () async {
                    // Pass driver specific ID instead of vendor's firebase_uid to avoid showing vendor notifications
                    final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
                    final driverIdStr = driverIdObj?.toString() ?? '';
                    final driverKycUid = driverIdStr.isNotEmpty ? 'driver_$driverIdStr' : '';
                    
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DriverNotificationsScreen(
                          firebaseUid: driverKycUid,
                        ),
                      ),
                    );
                    _fetchNotificationsCount(); // Refresh count after returning
                  },
                  tooltip: 'Notifications',
                ),
              ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: const Text(
                'Welcome to TRUCK UNION',
                style: TextStyle(
                  fontSize: 14,
                  color: AppTheme.textSecondary,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.5,
                ),
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
                  _buildImageSlide('assets/images/kyc.png'),
                  _buildImageSlide('assets/images/24.png'),
                  _buildImageSlide('assets/images/track.png'),
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

            // Quick Actions
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _buildQuickActionIcon(
                    icon: Icons.local_shipping_rounded,
                    label: 'My Orders',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DriverOrdersScreen(driverData: widget.driverData),
                      ),
                    ),
                  ),
                  _buildQuickActionIcon(
                    icon: Icons.verified_user_rounded,
                    label: 'KYC Status',
                    onTap: () async {
                      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
                      final driverId = driverIdObj?.toString() ?? '';
                      final driverKycUid = driverId.isNotEmpty ? 'driver_$driverId' : '';
                      
                      final result = await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => DriverKYCScreen(
                            firebaseUid: driverKycUid,
                            driverName: _getDriverName(driver),
                            driverMobile: driver['driver_mobile'] ?? driver['vendor_phone'] ?? '',
                            vehicleNumber: driver['vehicle_number']?.toString() ?? '',
                          ),
                        ),
                      );
                      // If KYC was submitted successfully, refresh the status
                      if (result == true) {
                        _fetchKycStatus();
                      }
                    },
                  ),
                  _buildQuickActionIcon(
                    icon: Icons.person_rounded,
                    label: 'Profile',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DriverProfileScreen(driverData: widget.driverData),
                      ),
                    ),
                  ),
                  _buildQuickActionIcon(
                    icon: Icons.support_agent_rounded,
                    label: 'Support',
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DriverSupportScreen(
                          driverData: widget.driverData,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),
            
            // Why Drivers Choose Us Section
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Why Drivers Choose Us',
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
                    description: 'Get consistent orders and grow your income with us',
                  ),
                  const SizedBox(height: 12),
                  _buildBenefitCard(
                    icon: Icons.security_rounded,
                    title: 'Secure Payments',
                    description: 'Fast and reliable payment processing every time',
                  ),
                  const SizedBox(height: 12),
                  _buildBenefitCard(
                    icon: Icons.support_agent_rounded,
                    title: '24/7 Support',
                    description: 'Dedicated support team always ready to help',
                  ),
                  const SizedBox(height: 12),
                  _buildBenefitCard(
                    icon: Icons.analytics_rounded,
                    title: 'Real-time Routing',
                    description: 'Optimized delivery paths with detailed insights',
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 32),
          ],
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
    Widget? trailing,
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
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: Colors.white, size: 36),
              if (trailing != null) trailing,
            ],
          ),
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

  Future<void> _handleLogout(BuildContext context) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Logout'),
          ),
        ],
      ),
    );

    if (confirm == true && mounted) {
      context.go('/role-selection');
    }
  }

  Widget _buildBenefitCard({
    required IconData icon,
    required String title,
    required String description,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [
            Color(0xFF0D2E6E),
            Color(0xFF1E40AF),
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
}
