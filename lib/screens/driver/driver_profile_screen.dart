import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../config/app_theme.dart';
import '../../services/api_service.dart';
import 'driver_kyc_screen.dart';
import 'driver_faq_screen.dart';
import 'driver_notifications_screen.dart';

class DriverProfileScreen extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverProfileScreen({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverProfileScreen> createState() => _DriverProfileScreenState();
}

class _DriverProfileScreenState extends State<DriverProfileScreen> {
  String _kycStatus = 'pending';
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadKYCStatus();
  }

  Future<void> _loadKYCStatus() async {
    try {
      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
      final driverId = driverIdObj?.toString() ?? '';
      final driverKycUid = driverId.isNotEmpty ? 'driver_$driverId' : '';
      
      final result = await ApiService.getDriverKYCStatus(
        firebaseUid: driverKycUid,
      );
      if (result['status'] == 'success' && result['kyc_exists'] == true && result['kyc_data'] != null) {
        setState(() {
          _kycStatus = result['kyc_data']['kyc_status'] ?? 'pending';
          _isLoading = false;
        });
      } else {
        setState(() {
          _kycStatus = 'not_submitted';
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Error loading KYC status: $e');
      setState(() => _isLoading = false);
    }
  }

  Color _getKYCStatusColor() {
    switch (_kycStatus) {
      case 'verified':
        return AppTheme.accentGreen;
      case 'under_review':
      case 'submitted':
      case 'not_submitted':
      case 'pending':
        return AppTheme.accentOrange;
      case 'rejected':
        return Colors.red;
      default:
        return AppTheme.accentOrange;
    }
  }

  String _getKYCStatusText() {
    switch (_kycStatus.toLowerCase()) {
      case 'verified':
        return 'Verified ✓';
      case 'under_review':
      case 'pending':
        return 'Under Verification';
      case 'submitted':
        return 'Submitted';
      case 'rejected':
        return 'Rejected';
      case 'not_submitted':
        return 'Not Submitted';
      default:
        return 'Under Verification';
    }
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
        title: const Text('My Profile'),
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        flexibleSpace: Container(
          decoration: BoxDecoration(gradient: AppTheme.primaryGradient),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  // Profile Header
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF0D2E6E), Color(0xFF1E5BA8)],
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
                    child: Row(
                      children: [
                        Container(
                          width: 70,
                          height: 70,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 3),
                          ),
                          child: const Icon(
                            Icons.person,
                            size: 36,
                            color: Color(0xFF0D2E6E),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _getDriverName(driver),
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                driver['driver_mobile'] ?? '',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Colors.white.withOpacity(0.9),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.2),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  driver['vehicle_number'] ?? '',
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // KYC Verification Section
                  _buildMenuOption(
                    icon: Icons.verified_user_rounded,
                    title: 'KYC Verification',
                    subtitle: 'Status: ${_getKYCStatusText()}',
                    color: _getKYCStatusColor(),
                    onTap: () {
                      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
                      final driverId = driverIdObj?.toString() ?? '';
                      final driverKycUid = driverId.isNotEmpty ? 'driver_$driverId' : '';
                      
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => DriverKYCScreen(
                            firebaseUid: driverKycUid,
                            driverName: _getDriverName(driver),
                            driverMobile: driver['driver_mobile'] ?? driver['vendor_phone'] ?? '',
                            vehicleNumber: driver['vehicle_number']?.toString() ?? '',
                          ),
                        ),
                      ).then((_) => _loadKYCStatus());
                    },
                  ),

                  const SizedBox(height: 12),

                  // Profile Section
                  _buildMenuOption(
                    icon: Icons.person_outline_rounded,
                    title: 'My Profile',
                    subtitle: 'View and edit your details',
                    color: Colors.teal,
                    onTap: () => _showProfileDetails(context, driver),
                  ),

                  const SizedBox(height: 12),

                  // Notifications Section
                  _buildMenuOption(
                    icon: Icons.notifications_none_rounded,
                    title: 'Notifications',
                    subtitle: 'View your alerts and updates',
                    color: Colors.orange,
                    onTap: () {
                      final driverIdObj = widget.driverData['driver']?['id'] ?? widget.driverData['driver']?['vehicle_id'];
                      final driverIdStr = driverIdObj?.toString() ?? '';
                      final driverKycUid = driverIdStr.isNotEmpty ? 'driver_$driverIdStr' : '';
                      
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => DriverNotificationsScreen(
                            firebaseUid: driverKycUid,
                          ),
                        ),
                      );
                    },
                  ),

                  const SizedBox(height: 12),

                  // FAQs Section
                  _buildMenuOption(
                    icon: Icons.help_outline_rounded,
                    title: 'FAQs',
                    subtitle: 'Frequently asked questions',
                    color: Colors.indigo,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const DriverFAQScreen(),
                      ),
                    ),
                  ),

                  const SizedBox(height: 12),

                  // About Section
                  _buildMenuOption(
                    icon: Icons.info_outline_rounded,
                    title: 'About',
                    subtitle: 'Version 1.0.0',
                    color: AppTheme.accentPurple,
                    onTap: () => _showAboutDialog(context),
                  ),

                  const SizedBox(height: 12),

                  // Delete Account Section
                  _buildMenuOption(
                    icon: Icons.delete_forever_rounded,
                    title: 'Delete Account',
                    subtitle: 'Permanently delete your account',
                    color: Colors.red,
                    onTap: () => _showDeleteAccountDialog(context),
                  ),

                  const SizedBox(height: 12),

                  // Logout Section
                  _buildMenuOption(
                    icon: Icons.logout_rounded,
                    title: 'Logout',
                    subtitle: 'Sign out from your account',
                    color: Colors.red,
                    onTap: () => _handleLogout(context),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildMenuOption({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
    Color? color,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: AppTheme.cardShadow,
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: (color ?? AppTheme.primaryBlue).withOpacity(0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: color ?? AppTheme.primaryBlue, size: 24),
        ),
        title: Text(
          title,
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: color ?? AppTheme.textPrimary,
          ),
        ),
        subtitle: Text(
          subtitle,
          style: TextStyle(
            fontSize: 13,
            color: Colors.grey[600],
          ),
        ),
        trailing: Icon(
          Icons.arrow_forward_ios_rounded,
          size: 16,
          color: Colors.grey[400],
        ),
        onTap: onTap,
      ),
    );
  }

  void _showProfileDetails(BuildContext context, Map<String, dynamic> driver) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Profile Details',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
            const SizedBox(height: 20),
            _buildProfileDetailRow('Name', _getDriverName(driver)),
            _buildProfileDetailRow('Mobile', driver['driver_mobile'] ?? 'N/A'),
            _buildProfileDetailRow(
              'Email',
              driver['driver_email'] ??
                  driver['email'] ??
                  driver['vendor_email'] ??
                  'Not provided',
            ),
            _buildProfileDetailRow('Vehicle Number', driver['vehicle_number'] ?? 'N/A'),
            _buildProfileDetailRow('Vehicle Type', driver['vehicle_type'] ?? 'N/A'),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryBlue,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'Close',
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[600],
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showAboutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('About TRUCK UNION'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Version: 1.0.0'),
            SizedBox(height: 8),
            Text('Driver App for TRUCK UNION'),
            SizedBox(height: 8),
            Text('© 2024 TRUCK UNION. All rights reserved.'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  void _showDeleteAccountDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Colors.red, size: 28),
            SizedBox(width: 12),
            Text('Delete Account?'),
          ],
        ),
        content: const Text(
          'This will permanently delete your driver account and all associated data.\n\n'
          'To proceed, please contact your vendor or support team at:\n'
          'support@abra-logistic.com\n\nThis action cannot be undone.',
          style: TextStyle(fontSize: 14, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              // Clear session and log out
              final prefs = await SharedPreferences.getInstance();
              await prefs.remove('driver_session');
              await prefs.remove('selected_role');
              if (context.mounted) context.go('/role-selection');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: const Text('Delete & Logout'),
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
      // Clear persisted driver session so next app open goes to login
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('driver_session');
      await prefs.remove('selected_role');
      if (mounted) context.go('/role-selection');
    }
  }
}
