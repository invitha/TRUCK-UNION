import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import '../../services/api_service.dart';
import '../../config/app_theme.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen>
    with SingleTickerProviderStateMixin {
  List<Map<String, dynamic>> _notifications = [];
  bool _isLoading = true;
  String? _errorMessage;
  Timer? _refreshTimer;
  late AnimationController _fadeCtrl;
  late Animation<double> _fadeAnim;

  @override
  void initState() {
    super.initState();
    _fadeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
    _fadeAnim = CurvedAnimation(parent: _fadeCtrl, curve: Curves.easeOut);
    _loadNotifications();
    _startAutoRefresh();
    Future.delayed(const Duration(seconds: 2), _markAllAsRead);
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _fadeCtrl.dispose();
    super.dispose();
  }

  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) _loadNotifications();
    });
  }

  Future<void> _loadNotifications() async {
    if (mounted) setState(() { _isLoading = true; _errorMessage = null; });

    final user = FirebaseAuth.instance.currentUser;
    if (user == null) {
      if (mounted) setState(() { _isLoading = false; _errorMessage = 'Not logged in'; });
      return;
    }

    try {
      final result = await ApiService.getNotifications(
        firebaseUid: user.uid,
        timestamp: DateTime.now().millisecondsSinceEpoch,
      );

      if (!mounted) return;

      if (result['status'] == 'success') {
        final List<dynamic> raw = result['notifications'] ?? result['data'] ?? [];
        final List<Map<String, dynamic>> notifs = raw
            .cast<Map<String, dynamic>>()
            .where((n) => !((n['title'] ?? '').toString().startsWith('[Driver]')))
            .toList();

        notifs.sort((a, b) {
          final aTime = DateTime.tryParse(a['created_at'] ?? '') ?? DateTime(0);
          final bTime = DateTime.tryParse(b['created_at'] ?? '') ?? DateTime(0);
          return bTime.compareTo(aTime);
        });

        setState(() {
          _notifications = notifs;
          _isLoading     = false;
          _errorMessage  = null;
        });
        _fadeCtrl.forward(from: 0);
      } else {
        setState(() {
          _isLoading    = false;
          _errorMessage = result['message']?.toString() ?? 'Failed to load notifications';
        });
      }
    } catch (e) {
      if (mounted) setState(() {
        _isLoading    = false;
        _errorMessage = 'Connection error. Check internet and try again.';
      });
    }
  }

  Future<void> _markAllAsRead() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;
    try {
      await ApiService.markNotificationRead(markAll: true, firebaseUid: user.uid);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt('unread_notifications_${user.uid}', 0);
    } catch (_) {}
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  int get _unreadCount =>
      _notifications.where((n) => !(n['is_read'] == true || n['is_read'] == 1 || n['is_read'] == '1')).length;

  _NotifStyle _styleForType(String type) {
    switch (type) {
      case 'order_assigned':
      case 'vehicle_assigned':
        return _NotifStyle(
          icon: Icons.local_shipping_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF0D2E6E), Color(0xFF1A4DB5)]),
          accent: const Color(0xFF0D2E6E),
          label: 'Shipment',
        );
      case 'kyc_approved':
        return _NotifStyle(
          icon: Icons.verified_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF059669), Color(0xFF10B981)]),
          accent: const Color(0xFF059669),
          label: 'KYC',
        );
      case 'kyc_rejected':
        return _NotifStyle(
          icon: Icons.cancel_rounded,
          gradient: const LinearGradient(colors: [Color(0xFFDC2626), Color(0xFFEF4444)]),
          accent: const Color(0xFFDC2626),
          label: 'KYC',
        );
      case 'kyc_revoked':
        return _NotifStyle(
          icon: Icons.warning_amber_rounded,
          gradient: const LinearGradient(colors: [Color(0xFFD97706), Color(0xFFF59E0B)]),
          accent: const Color(0xFFD97706),
          label: 'KYC',
        );
      case 'kyc_submitted':
        return _NotifStyle(
          icon: Icons.hourglass_top_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF0EA5E9), Color(0xFF38BDF8)]),
          accent: const Color(0xFF0EA5E9),
          label: 'KYC',
        );
      case 'vehicle_added':
        return _NotifStyle(
          icon: Icons.directions_car_rounded,
          gradient: const LinearGradient(colors: [Color(0xFFF59E0B), Color(0xFFFBBF24)]),
          accent: const Color(0xFFF59E0B),
          label: 'Vehicle',
        );
      case 'payment_received':
        return _NotifStyle(
          icon: Icons.currency_rupee_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF0D9488), Color(0xFF14B8A6)]),
          accent: const Color(0xFF0D9488),
          label: 'Payment',
        );
      case 'order_received':
        return _NotifStyle(
          icon: Icons.inventory_2_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF8B5CF6), Color(0xFFA78BFA)]),
          accent: const Color(0xFF8B5CF6),
          label: 'Order',
        );
      case 'order_update':
        return _NotifStyle(
          icon: Icons.update_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF0EA5E9), Color(0xFF38BDF8)]),
          accent: const Color(0xFF0EA5E9),
          label: 'Update',
        );
      default:
        return _NotifStyle(
          icon: Icons.notifications_rounded,
          gradient: const LinearGradient(colors: [Color(0xFF475569), Color(0xFF64748B)]),
          accent: const Color(0xFF475569),
          label: 'Notice',
        );
    }
  }

  String _dateLabel(String raw) {
    try {
      final dt  = DateTime.parse(raw);
      final now = DateTime.now();
      final today     = DateTime(now.year, now.month, now.day);
      final yesterday = today.subtract(const Duration(days: 1));
      final day       = DateTime(dt.year, dt.month, dt.day);
      if (day == today)     return 'Today';
      if (day == yesterday) return 'Yesterday';
      const months = ['', 'Jan','Feb','Mar','Apr','May','Jun',
                           'Jul','Aug','Sep','Oct','Nov','Dec'];
      return '${dt.day} ${months[dt.month]}';
    } catch (_) {
      return '';
    }
  }

  String _timeLabel(String raw) {
    try {
      final dt   = DateTime.parse(raw);
      final diff = DateTime.now().difference(dt);
      String rel;
      if (diff.inSeconds < 60)       rel = 'Just now';
      else if (diff.inMinutes < 60)  rel = '${diff.inMinutes}m ago';
      else if (diff.inHours   < 24)  rel = '${diff.inHours}h ago';
      else if (diff.inDays    <  7)  rel = '${diff.inDays}d ago';
      else                           rel = '';
      final hour12 = dt.hour == 0 ? 12 : (dt.hour > 12 ? dt.hour - 12 : dt.hour);
      final ampm   = dt.hour >= 12 ? 'PM' : 'AM';
      final min    = dt.minute.toString().padLeft(2, '0');
      final abs    = '$hour12:$min $ampm';
      return rel.isEmpty ? abs : '$rel  ·  $abs';
    } catch (_) {
      return raw;
    }
  }

  // ── Build ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4FF),
      appBar: _buildAppBar(),
      body: _isLoading
          ? _buildLoading()
          : _errorMessage != null
              ? _buildError(_errorMessage!)
              : _notifications.isEmpty
                  ? _buildEmpty()
                  : FadeTransition(
                      opacity: _fadeAnim,
                      child: RefreshIndicator(
                        color: AppTheme.primaryBlue,
                        onRefresh: _loadNotifications,
                        child: _buildList(),
                      ),
                    ),
    );
  }

  PreferredSizeWidget _buildAppBar() {
    final unread = _unreadCount;
    return AppBar(
      backgroundColor: Colors.transparent,
      elevation: 0,
      foregroundColor: Colors.white,
      flexibleSpace: Container(
        decoration: BoxDecoration(gradient: AppTheme.primaryGradient),
      ),
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_ios_rounded),
        onPressed: () => context.canPop() ? context.pop() : context.go('/vendor'),
      ),
      title: Row(
        children: [
          const Text('Notifications',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          if (unread > 0) ...[
            const SizedBox(width: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.25),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text('$unread new',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            ),
          ],
        ],
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.refresh_rounded),
          onPressed: _loadNotifications,
          tooltip: 'Refresh',
        ),
      ],
    );
  }

  Widget _buildLoading() {
    return const Center(
      child: CircularProgressIndicator(color: AppTheme.primaryBlue),
    );
  }

  Widget _buildError(String msg) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.wifi_off_rounded, size: 48, color: Colors.red.shade400),
            ),
            const SizedBox(height: 20),
            const Text('Could not load notifications',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E293B))),
            const SizedBox(height: 8),
            Text(msg,
                style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                textAlign: TextAlign.center),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _loadNotifications,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Try Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primaryBlue,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 13),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: AppTheme.primaryBlue.withOpacity(0.08),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.notifications_none_rounded,
                size: 64, color: AppTheme.primaryBlue),
          ),
          const SizedBox(height: 20),
          const Text('All Caught Up!',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF1E293B))),
          const SizedBox(height: 8),
          const Text("No new notifications right now.",
              style: TextStyle(fontSize: 14, color: Color(0xFF64748B))),
          const SizedBox(height: 4),
          const Text("Pull down to refresh.",
              style: TextStyle(fontSize: 13, color: Color(0xFF94A3B8))),
        ],
      ),
    );
  }

  Widget _buildList() {
    final items = <Widget>[];
    String? lastLabel;

    for (final notif in _notifications) {
      final label = _dateLabel(notif['created_at'] ?? '');
      if (label != lastLabel) {
        items.add(_buildDateHeader(label));
        lastLabel = label;
      }
      items.add(_buildCard(notif));
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: items,
    );
  }

  Widget _buildDateHeader(String label) {
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 6, left: 4),
      child: Row(
        children: [
          Text(label,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: Color(0xFF64748B),
                letterSpacing: 0.8,
              )),
          const SizedBox(width: 8),
          Expanded(child: Divider(color: Colors.grey.shade300, height: 1)),
        ],
      ),
    );
  }

  Widget _buildCard(Map<String, dynamic> notification) {
    final isRead = notification['is_read'] == true ||
        notification['is_read'] == 1 ||
        notification['is_read'] == '1';
    final type  = notification['type'] ?? 'system';
    final style = _styleForType(type);
    final time  = _timeLabel(notification['created_at'] ?? '');
    final title = (notification['title'] ?? 'Notification').toString();
    final msg   = (notification['message'] ?? '').toString();

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: isRead
            ? Border.all(color: const Color(0xFFE2E8F0), width: 1)
            : Border(left: BorderSide(color: style.accent, width: 4)),
        boxShadow: [
          BoxShadow(
            color: isRead
                ? Colors.black.withOpacity(0.04)
                : style.accent.withOpacity(0.10),
            blurRadius: isRead ? 4 : 12,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: InkWell(
        onTap: () async {
          if (!isRead) {
            final user = FirebaseAuth.instance.currentUser;
            if (user != null) {
              await ApiService.markNotificationRead(
                firebaseUid: user.uid,
                notificationId: notification['id'],
              );
              _loadNotifications();
            }
          }
        },
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon bubble
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  gradient: style.gradient,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: style.accent.withOpacity(0.3),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Icon(style.icon, color: Colors.white, size: 22),
              ),
              const SizedBox(width: 12),
              // Content
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: isRead ? FontWeight.w600 : FontWeight.bold,
                              color: const Color(0xFF1E293B),
                              height: 1.3,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (!isRead)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                            decoration: BoxDecoration(
                              color: style.accent.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text('NEW',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                  color: style.accent,
                                  letterSpacing: 0.5,
                                )),
                          ),
                      ],
                    ),
                    if (msg.isNotEmpty) ...[
                      const SizedBox(height: 5),
                      Text(
                        msg,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF475569),
                          height: 1.4,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            style.label,
                            style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF64748B),
                              letterSpacing: 0.4,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.access_time_rounded, size: 12, color: Color(0xFF94A3B8)),
                        const SizedBox(width: 3),
                        Text(time,
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFF94A3B8),
                            )),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotifStyle {
  final IconData icon;
  final LinearGradient gradient;
  final Color accent;
  final String label;
  const _NotifStyle({
    required this.icon,
    required this.gradient,
    required this.accent,
    required this.label,
  });
}
