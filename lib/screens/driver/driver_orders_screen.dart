import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:async';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../config/app_theme.dart';
import 'proof_of_delivery_screen.dart';


class _StatusStep {
  final String fromStatus;
  final String toStatus;
  final IconData icon;
  final Color color;
  const _StatusStep(this.fromStatus, this.toStatus, this.icon, this.color);
  String get currentStatus => fromStatus;
  String get nextStatus => toStatus;
}
class DriverOrdersScreen extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverOrdersScreen({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverOrdersScreen> createState() => _DriverOrdersScreenState();
}

class _DriverOrdersScreenState extends State<DriverOrdersScreen> {
  List<Map<String, dynamic>> _allOrders = [];
  List<Map<String, dynamic>> _completedOrders = [];
  bool _isLoading = true;
  Timer? _refreshTimer;

  // ── New 7-step pipeline (steps 2–6 are one-tap, steps 3 & 6 require POD photo) ──
  // Step 1: Pickup Assigned      → Accept / Decline (handled separately)
  // Step 2: Pickup Accepted      → tap → Reaching at Loading Point
  // Step 3: Reaching at Loading Point → tap → Picked Up  (POD required)
  // Step 4: Picked Up            → tap → Out for Delivery
  // Step 5: Out for Delivery     → tap → Reached at Unloading Point
  // Step 6: Reached at Unloading Point → tap → Delivered  (POD required)
  static const List<_StatusStep> _pipeline = [
    _StatusStep('Pickup Accepted',             'Reaching at Loading Point', Icons.directions_car_rounded,   Color(0xFF0EA5E9)),
    _StatusStep('Reaching at Loading Point',   'Picked Up',                 Icons.camera_alt_rounded,       Color(0xFFF59E0B)),
    _StatusStep('Picked Up',                   'Out for Delivery',          Icons.local_shipping_rounded,   Color(0xFF7C3AED)),
    _StatusStep('Out for Delivery',            'Reached at Unloading Point',Icons.location_on_rounded,     Color(0xFFEF4444)),
    _StatusStep('Reached at Unloading Point',  'Delivered',                 Icons.camera_alt_rounded,       Color(0xFF16A34A)),
  ];

  // Steps that require a POD photo instead of a simple tap
  static const _podSteps = {
    'reaching at loading point': 'pickup',   // next = Picked Up
    'reached at unloading point': 'delivery', // next = Delivered
  };

  @override
  void initState() {
    super.initState();
    _loadOrders();
    _startAutoRefresh();
    // Fallback: if loading hasn't resolved after 12s, force stop spinner
    Future.delayed(const Duration(seconds: 12), () {
      if (mounted && _isLoading) {
        setState(() => _isLoading = false);
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      if (mounted) _loadOrders();
    });
  }

  Future<void> _loadOrders() async {
    try {
      final vehicleId = widget.driverData['driver']['vehicle_id'];
      final activeResult    = await ApiService.getDriverOrdersEnhanced(vehicleId: vehicleId, orderType: 'active');
      final completedResult = await ApiService.getDriverOrdersEnhanced(vehicleId: vehicleId, orderType: 'completed');
      if (mounted) {
        setState(() {
          _allOrders       = activeResult['status'] == 'success'
              ? List<Map<String, dynamic>>.from(activeResult['orders'] ?? [])
              : [];
          _completedOrders = completedResult['status'] == 'success'
              ? List<Map<String, dynamic>>.from(completedResult['orders'] ?? [])
              : [];
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // ── Status helpers ───────────────────────────────────────────────────────

  bool _needsAcceptance(String s) {
    final v = s.toLowerCase().trim();
    return v == 'pickup assigned' || v == 'pickup_assigned' ||
        v == 'assigned' || v == 'awb created' ||
        v == 'pending pickup' || v == 'pending_pickup';
  }

  _StatusStep? _getPipelineStep(String rawStatus) {
    final s = rawStatus.toLowerCase().trim();
    for (final step in _pipeline) {
      if (step.currentStatus.toLowerCase() == s) return step;
    }
    return null;
  }

  /// Returns the POD type if this step needs a photo, else null
  String? _podTypeForStep(_StatusStep step) {
    return _podSteps[step.currentStatus.toLowerCase()];
  }

  Color _getLoadColor(String category) {
    switch (category) {
      case 'express': return const Color(0xFFEF4444);
      case 'ftl':     return const Color(0xFF7C3AED);
      default:        return const Color(0xFF0D2E6E);
    }
  }

  String _getLoadLabel(String category) {
    switch (category) {
      case 'express': return 'Express';
      case 'ftl':     return 'Full Load (FTL)';
      default:        return 'Part Load';
    }
  }

  Color _getStatusColor(String s) {
    final v = s.toLowerCase();
    if (_needsAcceptance(v))                  return const Color(0xFF6B7280);
    if (v == 'pickup accepted')               return const Color(0xFF0EA5E9);
    if (v == 'pickup declined')               return const Color(0xFFEF4444);
    if (v.contains('reaching at loading'))    return const Color(0xFFF59E0B);
    if (v.contains('picked up'))              return const Color(0xFF7C3AED);
    if (v.contains('out for delivery'))       return const Color(0xFFEF4444);
    if (v.contains('reached at unloading'))   return const Color(0xFFEF4444);
    if (v.contains('delivered'))              return const Color(0xFF16A34A);
    return Colors.grey;
  }

  // ── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: const Color(0xFFF0F2F7),
        appBar: AppBar(
          title: const Text(
            'My Orders',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
          ),
          backgroundColor: const Color(0xFF0D2E6E),
          foregroundColor: Colors.white,
          elevation: 0,
          bottom: TabBar(
            indicatorColor: Colors.white,
            indicatorWeight: 3,
            indicatorSize: TabBarIndicatorSize.tab,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white60,
            labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, letterSpacing: 0.2),
            unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
            tabs: [
              const Tab(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.inventory_2_outlined, size: 15),
                    SizedBox(width: 5),
                    Text('Part Load'),
                  ],
                ),
              ),
              const Tab(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.local_shipping_rounded, size: 15),
                    SizedBox(width: 5),
                    Text('Full Load'),
                  ],
                ),
              ),
              Tab(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.check_circle_rounded, size: 15),
                    const SizedBox(width: 5),
                    Text('Done (${_completedOrders.length})'),
                  ],
                ),
              ),
            ],
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh_rounded),
              onPressed: _loadOrders,
              tooltip: 'Refresh',
            ),
          ],
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(children: [
                _buildOrderList('part_load'),
                _buildOrderList('ftl'),
                _buildCompletedList(),
              ]),
      ),
    );
  }

  Widget _buildOrderList(String category) {
    final orders = _allOrders.where((o) => o['load_category'] == category).toList();

    if (orders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.inbox_rounded, size: 80, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No ${_getLoadLabel(category)} orders', style: AppTheme.heading2),
            const SizedBox(height: 8),
            Text('Pull down to refresh', style: AppTheme.caption),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadOrders,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: orders.length,
        itemBuilder: (ctx, i) => _buildOrderCard(orders[i]),
      ),
    );
  }

  Widget _buildCompletedList() {
    if (_completedOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle_outline_rounded, size: 80, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No completed orders yet', style: AppTheme.heading2),
            const SizedBox(height: 8),
            Text('Delivered orders will appear here', style: AppTheme.caption),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadOrders,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _completedOrders.length,
        itemBuilder: (ctx, i) => _buildCompletedCard(_completedOrders[i]),
      ),
    );
  }

  Widget _buildCompletedCard(Map<String, dynamic> order) {
    final trackingNumber = order['tracking_number']?.toString() ?? order['al_number'] ?? 'N/A';
    final alNumber   = order['al_number']   ?? '';
    final rawStatus  = order['status']?.toString() ?? 'Delivered';
    final receiver   = order['receiver_name']?.toString() ?? '';
    final dest       = order['receiver_address']?.toString() ?? order['dest_pincode']?.toString() ?? '';
    final isDelivered = rawStatus.toLowerCase().contains('delivered');

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDelivered ? Colors.green.shade100 : Colors.grey.shade200),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
              color: (isDelivered ? Colors.green : Colors.grey).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              isDelivered ? Icons.check_circle_rounded : Icons.cancel_rounded,
              color: isDelivered ? Colors.green : Colors.grey,
              size: 24,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trackingNumber,
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF0D2E6E)),
                ),
                if (alNumber.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text('AL: $alNumber', style: TextStyle(fontSize: 11, color: Colors.grey[600])),
                ],
                if (receiver.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(receiver, style: TextStyle(fontSize: 12, color: Colors.grey[700])),
                ],
                if (dest.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(dest, style: TextStyle(fontSize: 11, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
                ],
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: (isDelivered ? Colors.green : Colors.grey).withOpacity(0.1),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              rawStatus.toUpperCase(),
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: isDelivered ? Colors.green.shade700 : Colors.grey,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderCard(Map<String, dynamic> order) {
    final alNumber      = order['al_number'] ?? 'N/A';
    final trackingNumber = order['tracking_number']?.toString() ?? alNumber;
    final category    = order['load_category'] ?? 'part_load';
    final rawStatus   = order['status']?.toString() ?? 'Unknown';
    final catColor    = _getLoadColor(category);
    final statusColor = _getStatusColor(rawStatus);
    final needsAccept = _needsAcceptance(rawStatus);
    final pipeStep    = _getPipelineStep(rawStatus);
    final navAddress  = _getNavAddress(order, rawStatus);
    final hasAction   = needsAccept || pipeStep != null || navAddress != null;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Left accent bar
          Container(
            width: 5,
            decoration: BoxDecoration(
              color: catColor,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(14),
                bottomLeft: Radius.circular(14),
              ),
            ),
          ),
          // Card body
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
                children: [
                  InkWell(
                    onTap: () => _showOrderDetails(order),
                    borderRadius: const BorderRadius.only(topRight: Radius.circular(14)),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Row(
                            children: [
                              _typeBadge(category, catColor),
                              const SizedBox(width: 8),
                              _statusBadge(rawStatus, statusColor),
                              const Spacer(),
                              Icon(Icons.chevron_right_rounded, size: 18, color: Colors.grey[400]),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              const Icon(Icons.qr_code_rounded, size: 14, color: Color(0xFF0D2E6E)),
                              const SizedBox(width: 4),
                              Text(
                                trackingNumber,
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF0D2E6E),
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text('AL: $alNumber', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                          const SizedBox(height: 6),
                          Row(children: [
                            Container(width: 7, height: 7, decoration: const BoxDecoration(color: Color(0xFF10B981), shape: BoxShape.circle)),
                            const SizedBox(width: 5),
                            const Text('Pickup', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFF10B981))),
                          ]),
                          const SizedBox(height: 1),
                          _addressRow(Icons.radio_button_checked_rounded, const Color(0xFF10B981), order['sender_address'] ?? 'N/A'),
                          const SizedBox(height: 4),
                          Row(children: [
                            Container(width: 7, height: 7, decoration: const BoxDecoration(color: Color(0xFFEF4444), shape: BoxShape.circle)),
                            const SizedBox(width: 5),
                            const Text('Delivery', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFFEF4444))),
                          ]),
                          const SizedBox(height: 2),
                          _addressRow(Icons.location_on_rounded, const Color(0xFFEF4444), order['receiver_address'] ?? 'N/A'),
                        ],
                      ),
                    ),
                  ),

                  // Action area
                  if (hasAction)
                    Container(
                      decoration: const BoxDecoration(
                        color: Color(0xFFF8F9FC),
                        borderRadius: BorderRadius.only(bottomRight: Radius.circular(14)),
                        border: Border(top: BorderSide(color: Color(0xFFEEF0F5))),
                      ),
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
                      child: Column(
                        children: [
                          if (needsAccept)
                            _acceptDeclineRow(order),
                          if (pipeStep != null)
                            _buildPipelineAction(pipeStep, order, inBottomSheet: false),
                          if (navAddress != null && navAddress.isNotEmpty && navAddress != 'N/A') ...[
                            const SizedBox(height: 8),
                            _navButton(order, rawStatus, navAddress),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
    );
  }

  // ── Accept / Decline ─────────────────────────────────────────────────────

  Widget _acceptDeclineRow(Map<String, dynamic> order) {
    return Row(
      children: [
        Expanded(
          child: SizedBox(
            height: 44,
            child: OutlinedButton.icon(
              onPressed: () => _respondToOrder(order, accept: false),
              icon: const Icon(Icons.close_rounded, size: 18),
              label: const Text('Decline', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFFEF4444),
                side: const BorderSide(color: Color(0xFFEF4444)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: SizedBox(
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () => _respondToOrder(order, accept: true),
              icon: const Icon(Icons.check_rounded, size: 18),
              label: const Text('Accept', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10B981),
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _respondToOrder(Map<String, dynamic> order, {required bool accept}) async {
    final newStatus = accept ? 'Pickup Accepted' : 'Pickup Declined';
    final result = await ApiService.updateOrderStatusEnhanced(
      alNumber: order['al_number'],
      vehicleId: widget.driverData['driver']['vehicle_id'],
      status: newStatus,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result['status'] == 'success'
            ? (accept ? 'Order accepted — head to loading point' : 'Order declined')
            : result['message'] ?? 'Failed to update'),
        backgroundColor: result['status'] == 'success'
            ? (accept ? const Color(0xFF10B981) : const Color(0xFFEF4444))
            : Colors.red,
      ),
    );
    if (result['status'] == 'success') _loadOrders();
  }

  // ── Pipeline action button (tap or POD photo) ─────────────────────────────

  Widget _buildPipelineAction(_StatusStep step, Map<String, dynamic> order, {required bool inBottomSheet}) {
    final podType = _podTypeForStep(step);
    final isPod   = podType != null;

    String label;
    IconData icon;
    Color color;

    if (isPod) {
      // Photo required
      label = podType == 'pickup'
          ? 'Take Pickup Photo → Mark as ${step.nextStatus}'
          : 'Take Delivery Photo → Mark as ${step.nextStatus}';
      icon  = Icons.camera_alt_rounded;
      color = podType == 'pickup' ? const Color(0xFFF59E0B) : const Color(0xFF16A34A);
    } else {
      label = 'Mark as ${step.nextStatus}';
      icon  = step.icon;
      color = step.color;
    }

    return SizedBox(
      width: double.infinity,
      height: 44,
      child: ElevatedButton.icon(
        onPressed: () {
          if (inBottomSheet) Navigator.pop(context);
          if (isPod) {
            // POD steps (Picked Up / Delivered) require OTP first, then photo
            final otpType = podType == 'pickup' ? 'pickup' : 'delivery';
            _showOtpDialog(order, otpType, onVerified: () => _navigateToPOD(order, podType!));
          } else {
            _updateStatus(order, step.nextStatus);
          }
        },
        icon: Icon(icon, size: 18),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
    );
  }

  // ── Small widgets ─────────────────────────────────────────────────────────

  // ── Navigation button ─────────────────────────────────────────────────────

  Widget _navButton(Map<String, dynamic> order, String rawStatus, String address) {
    return SizedBox(
      width: double.infinity,
      height: 42,
      child: OutlinedButton.icon(
        onPressed: () => _navigateToAddress(address),
        icon: const Icon(Icons.navigation_rounded, size: 18),
        label: Text(
          _navLabel(rawStatus),
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        ),
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF4285F4),
          side: const BorderSide(color: Color(0xFF4285F4)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
    );
  }

  Widget _typeBadge(String category, Color color) {
    final icons = {
      'express': Icons.flash_on_rounded,
      'ftl': Icons.local_shipping_rounded,
      'part_load': Icons.inventory_2_outlined,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icons[category] ?? Icons.inventory_2_outlined, size: 12, color: color),
          const SizedBox(width: 4),
          Text(_getLoadLabel(category),
              style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }

  Widget _statusBadge(String status, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(status,
          style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }

  Widget _addressRow(IconData icon, Color color, String text) {
    return Row(
      children: [
        Icon(icon, size: 13, color: color),
        const SizedBox(width: 6),
        Expanded(
          child: Text(text,
              style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
              maxLines: 1,
              overflow: TextOverflow.ellipsis),
        ),
      ],
    );
  }

  // ── Order detail bottom sheet ─────────────────────────────────────────────

  void _showOrderDetails(Map<String, dynamic> order) {
    final rawStatus   = order['status']?.toString() ?? 'Unknown';
    final needsAccept = _needsAcceptance(rawStatus);
    final pipeStep    = _getPipelineStep(rawStatus);
    final navAddress  = _getNavAddress(order, rawStatus);
    final category    = order['load_category'] ?? 'part_load';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        initialChildSize: 0.75,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        builder: (_, controller) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            children: [
              Container(
                margin: const EdgeInsets.symmetric(vertical: 12),
                width: 40, height: 4,
                decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)),
              ),
              Expanded(
                child: ListView(
                  controller: controller,
                  padding: const EdgeInsets.all(20),
                  children: [
                    Text('Order Details', style: AppTheme.heading2),
                    const SizedBox(height: 16),
                    _buildStatusPipeline(rawStatus),
                    const SizedBox(height: 20),
                    _detailRow('AL Number',      order['al_number'] ?? 'N/A'),
                    _detailRow('Tracking',       order['tracking_number'] ?? 'N/A'),
                    _detailRow('Status',         rawStatus),
                    _detailRow('Load Type',      _getLoadLabel(category)),
                    _detailRow('Pickup',         order['sender_address'] ?? 'N/A'),
                    _detailRow('Delivery',       order['receiver_address'] ?? 'N/A'),
                    _detailRow('Sender',         order['sender_name'] ?? 'N/A'),
                    _buildContactButtons(
                      phone: order['sender_phone']?.toString() ?? order['sender_mobile']?.toString() ?? '',
                      label: 'Sender',
                    ),
                    _detailRow('Receiver',       order['receiver_name'] ?? 'N/A'),
                    _buildContactButtons(
                      phone: order['receiver_phone']?.toString() ?? order['receiver_mobile']?.toString() ?? '',
                      label: 'Receiver',
                    ),
                    if (order['weight'] != null)
                      _detailRow('Weight',       '${order['weight']} kg'),
                    if (order['amount'] != null)
                      _detailRow('Amount',       '₹${order['amount']}'),
                    const SizedBox(height: 20),
                    if (needsAccept) _acceptDeclineRow(order),
                    if (pipeStep != null)
                      _buildPipelineAction(pipeStep, order, inBottomSheet: true),
                    if (navAddress != null && navAddress.isNotEmpty && navAddress != 'N/A') ...[
                      const SizedBox(height: 8),
                      _navButton(order, rawStatus, navAddress),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── Status pipeline widget ────────────────────────────────────────────────

  Widget _buildStatusPipeline(String rawStatus) {
    final allSteps = [
      'Pickup Assigned',
      'Pickup Accepted',
      'Reaching at Loading Point',
      'Picked Up',
      'Out for Delivery',
      'Reached at Unloading Point',
      'Delivered',
    ];
    final currentIdx = allSteps.indexWhere(
      (s) => s.toLowerCase() == rawStatus.toLowerCase(),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Journey', style: AppTheme.bodyMedium.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 10),
        SizedBox(
          height: 56,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: allSteps.length,
            separatorBuilder: (_, __) => const Center(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 2),
                child: Icon(Icons.chevron_right_rounded, size: 16, color: Colors.grey),
              ),
            ),
            itemBuilder: (_, i) {
              final done    = currentIdx >= 0 && i < currentIdx;
              final current = i == currentIdx;
              return Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    decoration: BoxDecoration(
                      color: done ? AppTheme.accentGreen : current ? AppTheme.primaryBlue : Colors.grey[200],
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      allSteps[i],
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: current ? FontWeight.w800 : FontWeight.w500,
                        color: (done || current) ? Colors.white : Colors.grey[500],
                      ),
                    ),
                  ),
                  if (current)
                    Container(
                      margin: const EdgeInsets.only(top: 3),
                      width: 4, height: 4,
                      decoration: const BoxDecoration(color: AppTheme.primaryBlue, shape: BoxShape.circle),
                    ),
                ],
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildContactButtons({required String phone, required String label}) {
    if (phone.isEmpty || phone == 'null') return const SizedBox.shrink();

    // Normalise: strip spaces/dashes, ensure 10 digits for WhatsApp country code
    final clean = phone.replaceAll(RegExp(r'[\s\-()]'), '');
    final waNumber = clean.startsWith('+') ? clean.replaceFirst('+', '') : '91$clean';

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          SizedBox(
            width: 120,
            child: Text('$label Contact',
                style: AppTheme.bodyMedium.copyWith(
                    color: AppTheme.textSecondary, fontWeight: FontWeight.w500)),
          ),
          Expanded(
            child: Row(
              children: [
                // ── Call button ────────────────────────────────────────
                Expanded(
                  child: SizedBox(
                    height: 36,
                    child: ElevatedButton.icon(
                      onPressed: () async {
                        // Copy to clipboard AND open dialer
                        await Clipboard.setData(ClipboardData(text: clean));
                        final uri = Uri.parse('tel:$clean');
                        if (await canLaunchUrl(uri)) {
                          await launchUrl(uri);
                        } else {
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('$label number copied: $clean')),
                            );
                          }
                        }
                      },
                      icon: const Icon(Icons.call, size: 15),
                      label: const Text('Call', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0D2E6E),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                // ── WhatsApp button ────────────────────────────────────
                Expanded(
                  child: SizedBox(
                    height: 36,
                    child: ElevatedButton.icon(
                      onPressed: () async {
                        final uri = Uri.parse('https://wa.me/$waNumber');
                        if (await canLaunchUrl(uri)) {
                          await launchUrl(uri, mode: LaunchMode.externalApplication);
                        } else {
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('WhatsApp is not installed')),
                            );
                          }
                        }
                      },
                      icon: const Icon(Icons.chat, size: 15),
                      label: const Text('WhatsApp', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF25D366),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label, style: AppTheme.bodyMedium.copyWith(color: AppTheme.textSecondary, fontWeight: FontWeight.w500)),
          ),
          Expanded(
            child: Text(value, style: AppTheme.bodyMedium.copyWith(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }

  // ── Navigation & update ───────────────────────────────────────────────────

  Future<void> _navigateToAddress(String address) async {
    if (address.trim().isEmpty || address == 'N/A') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No address available for navigation')),
      );
      return;
    }
    final encoded   = Uri.encodeComponent(address);
    final googleUrl = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$encoded&travelmode=driving');
    final geoUrl    = Uri.parse('geo:0,0?q=$encoded');
    if (await canLaunchUrl(googleUrl)) {
      await launchUrl(googleUrl, mode: LaunchMode.externalApplication);
    } else if (await canLaunchUrl(geoUrl)) {
      await launchUrl(geoUrl, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open Maps. Please install Google Maps.')),
      );
    }
  }

  String? _getNavAddress(Map<String, dynamic> order, String rawStatus) {
    final v = rawStatus.toLowerCase();
    if (v == 'pickup accepted' || v == 'reaching at loading point') return order['sender_address']?.toString();
    if (v == 'picked up' || v == 'out for delivery' || v == 'reached at unloading point') return order['receiver_address']?.toString();
    return null;
  }

  String _navLabel(String rawStatus) {
    final v = rawStatus.toLowerCase();
    if (v == 'pickup accepted' || v == 'reaching at loading point') return 'Navigate to Pickup';
    return 'Navigate to Delivery';
  }

  // ── OTP Verification dialog ───────────────────────────────────────────────

  void _showOtpDialog(Map<String, dynamic> order, String otpType, {required VoidCallback onVerified}) {
    final ctrl     = TextEditingController();
    bool  _loading = false;
    String? _error;
    final isPickup = otpType == 'pickup';
    final color    = isPickup ? const Color(0xFFF59E0B) : const Color(0xFF16A34A);
    final label    = isPickup ? 'Pickup OTP' : 'Delivery OTP';

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => Dialog(
          insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          child: SingleChildScrollView(
            padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 64, height: 64,
                    decoration: BoxDecoration(color: color.withOpacity(0.12), shape: BoxShape.circle),
                    child: Icon(isPickup ? Icons.upload_rounded : Icons.download_rounded, color: color, size: 32),
                  ),
                  const SizedBox(height: 16),
                  Text('Enter $label', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Text(
                    'Ask the customer for their ${label.toLowerCase()} and enter it below.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
                  ),
                  const SizedBox(height: 20),
                  TextField(
                    controller: ctrl,
                    keyboardType: TextInputType.number,
                    maxLength: 4,
                    textAlign: TextAlign.center,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    style: TextStyle(fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: 12, color: color),
                    decoration: InputDecoration(
                      counterText: '',
                      hintText: '----',
                      hintStyle: TextStyle(fontSize: 32, letterSpacing: 12, color: Colors.grey.shade300),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: color, width: 2)),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey.shade300)),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                  ],
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          onPressed: _loading ? null : () => Navigator.pop(ctx),
                          child: const Text('Cancel'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: color,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          onPressed: _loading
                              ? null
                              : () async {
                                  if (ctrl.text.length != 4) {
                                    setDlgState(() => _error = 'Enter a 4-digit OTP');
                                    return;
                                  }
                                  setDlgState(() { _loading = true; _error = null; });
                                  try {
                                    final vid = widget.driverData['driver']['vehicle_id'];
                                    final trackingVal = order['tracking_number']?.toString() ?? order['al_number']?.toString() ?? '';
                                    final res = await ApiService.verifyOtp(
                                      trackingNumber: trackingVal,
                                      otpType: otpType,
                                      otpCode: ctrl.text,
                                      vehicleId: vid is int ? vid : int.parse(vid.toString()),
                                    );
                                    if (!mounted) return;
                                    if (res['status'] == 'success' || res['status'] == 'already_verified') {
                                      Navigator.pop(ctx);
                                      onVerified();
                                    } else {
                                      setDlgState(() { _loading = false; _error = res['message'] ?? 'Invalid OTP'; });
                                    }
                                  } catch (e) {
                                    if (mounted) setDlgState(() { _loading = false; _error = 'Something went wrong. Please try again.'; });
                                  }
                                },
                          child: _loading
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                              : const Text('Verify', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _navigateToPOD(Map<String, dynamic> order, String podType) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProofOfDeliveryScreen(
          order: order,
          driverData: widget.driverData,
          podType: podType,
        ),
      ),
    );
    if (result == true) _loadOrders();
  }

  Future<void> _updateStatus(Map<String, dynamic> order, String newStatus) async {
    final result = await ApiService.updateOrderStatusEnhanced(
      alNumber: order['al_number'],
      vehicleId: widget.driverData['driver']['vehicle_id'],
      status: newStatus,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result['status'] == 'success' ? 'Status updated to $newStatus' : result['message'] ?? 'Failed to update'),
        backgroundColor: result['status'] == 'success' ? const Color(0xFF10B981) : Colors.red,
      ),
    );
    if (result['status'] == 'success') _loadOrders();
  }
}
