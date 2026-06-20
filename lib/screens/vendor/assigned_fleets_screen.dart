import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import '../../config/app_theme.dart';
import '../../services/api_service.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

class AssignedFleetsScreen extends StatefulWidget {
  const AssignedFleetsScreen({super.key});

  @override
  State<AssignedFleetsScreen> createState() => _AssignedFleetsScreenState();
}

class _AssignedFleetsScreenState extends State<AssignedFleetsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<Map<String, dynamic>> _activeAssignments = [];
  List<Map<String, dynamic>> _completedAssignments = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadAssignments();
  }

  Future<void> _loadAssignments() async {
    setState(() => _isLoading = true);
    
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;

    try {
      // Load pending and active assignments for the "Active" tab
      final activeResponse = await ApiService.getFleetAssignments(
        firebaseUid: user.uid,
        statusFilter: 'active',  // This will include both pending and active for vendors
      );
      
      // Load completed assignments
      final completedResponse = await ApiService.getFleetAssignments(
        firebaseUid: user.uid,
        statusFilter: 'completed',
      );

      if (mounted) {
        setState(() {
          final allActive = activeResponse['status'] == 'success'
              ? List<Map<String, dynamic>>.from(activeResponse['assignments'] ?? [])
              : <Map<String, dynamic>>[];

          // Filter out assignments whose linked shipment was deleted from backend.
          // A deleted shipment means: tracking_numbers is set but pickup_location
          // AND delivery_location are both null/empty (the JOIN returned nothing).
          bool _shipmentExists(Map<String, dynamic> a) {
            final tracking  = (a['tracking_numbers']  ?? '').toString().trim();
            final pickup    = (a['pickup_location']   ?? '').toString().trim();
            final delivery  = (a['delivery_location'] ?? '').toString().trim();
            // If there is a tracking ref but no location data at all → shipment deleted
            if (tracking.isNotEmpty && pickup.isEmpty && delivery.isEmpty) return false;
            return true;
          }

          // Client-side safety: delivered shipments belong in Completed, not Active
          _activeAssignments = allActive.where((a) {
            if (!_shipmentExists(a)) return false;
            final cs = (a['courier_status'] ?? '').toString().toLowerCase();
            return !cs.contains('delivered') && !cs.contains('cancelled');
          }).toList();

          final allCompleted = completedResponse['status'] == 'success'
              ? List<Map<String, dynamic>>.from(completedResponse['assignments'] ?? [])
              : <Map<String, dynamic>>[];
          // Also pull delivered from active into completed list, excluding deleted shipments
          final deliveredFromActive = allActive.where((a) {
            if (!_shipmentExists(a)) return false;
            final cs = (a['courier_status'] ?? '').toString().toLowerCase();
            return cs.contains('delivered') || cs.contains('cancelled');
          }).toList();
          _completedAssignments = [
            ...allCompleted.where(_shipmentExists),
            ...deliveredFromActive,
          ];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading assignments: $e')),
        );
      }
    }
  }

  Future<void> _handleVendorAction(Map<String, dynamic> assignment, String newStatus) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final user = FirebaseAuth.instance.currentUser;
      if (user == null) return;
      
      final response = await ApiService.updateFleetAssignmentStatus(
        assignmentId: assignment['id'].toString(),
        firebaseUid: user.uid,
        status: newStatus,
      );

      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop(); // Close loading
        if (response['status'] == 'success') {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Successfully updated status to ${newStatus.toUpperCase()}'), backgroundColor: Colors.green),
          );
          _loadAssignments();
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(response['message'] ?? 'Failed to update'), backgroundColor: Colors.red),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop(); // Close loading
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      body: SafeArea(
        child: Column(
          children: [
            // Custom Header with gradient
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [Colors.white, AppTheme.backgroundLight],
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  ),
                                  borderRadius: BorderRadius.circular(12),
                                  boxShadow: [
                                    BoxShadow(
                                      color: AppTheme.primaryBlue.withOpacity(0.3),
                                      blurRadius: 8,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                child: const Icon(
                                  Icons.assignment_rounded,
                                  color: Colors.white,
                                  size: 22,
                                ),
                              ),
                              const SizedBox(width: 12),
                              const Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Assigned Fleets',
                                      style: TextStyle(
                                        color: AppTheme.primaryBlue,
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: -0.5,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                    Text(
                                      'Track your shipments',
                                      style: TextStyle(
                                        color: AppTheme.textSecondary,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        Container(
                          decoration: BoxDecoration(
                            color: AppTheme.primaryBlue.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: IconButton(
                            icon: const Icon(Icons.refresh_rounded, color: AppTheme.primaryBlue),
                            onPressed: _loadAssignments,
                            tooltip: 'Refresh',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    // Enhanced Tab Selector
                    Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.grey[100],
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey[200]!),
                      ),
                      child: TabBar(
                        controller: _tabController,
                        labelColor: Colors.white,
                        unselectedLabelColor: AppTheme.textSecondary,
                        indicator: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(10),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.primaryBlue.withOpacity(0.3),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        indicatorSize: TabBarIndicatorSize.tab,
                        dividerColor: Colors.transparent,
                        labelStyle: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          letterSpacing: 0.3,
                        ),
                        unselectedLabelStyle: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                        tabs: [
                          Tab(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.assignment_rounded, size: 15),
                                const SizedBox(width: 4),
                                Flexible(
                                  child: Text(
                                    'Active (${_activeAssignments.length})',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Tab(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.check_circle_rounded, size: 15),
                                const SizedBox(width: 4),
                                Flexible(
                                  child: Text(
                                    'Completed (${_completedAssignments.length})',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Tab Content
            Expanded(
              child: _isLoading
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const CircularProgressIndicator(
                            valueColor: AlwaysStoppedAnimation<Color>(AppTheme.primaryBlue),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Loading assignments...',
                            style: TextStyle(
                              color: Colors.grey[600],
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    )
                  : TabBarView(
                      controller: _tabController,
                      children: [
                        _buildAssignmentsList(_activeAssignments, isActive: true),
                        _buildAssignmentsList(_completedAssignments, isActive: false),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAssignmentsList(List<Map<String, dynamic>> assignments, {required bool isActive}) {
    if (assignments.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isActive ? Icons.local_shipping_outlined : Icons.history,
              size: 64,
              color: Colors.grey[400],
            ),
            const SizedBox(height: 16),
            Text(
              isActive ? 'No Pending or Active Assignments' : 'No Completed Assignments',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 8),
            Text(
              isActive 
                  ? 'New assignments from admin will appear here for you to accept or decline'
                  : 'Completed assignments will appear here',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[500],
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadAssignments,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: assignments.length,
        itemBuilder: (context, index) {
          final assignment = assignments[index];
          return _buildAssignmentCard(assignment, isActive);
        },
      ),
    );
  }

  Widget _buildAssignmentCard(Map<String, dynamic> assignment, bool isActive) {
    final status = assignment['status'] ?? 'active';
    final statusColor = _getStatusColor(status);
    final statusIcon = _getStatusIcon(status);

    final pickup = (assignment['pickup_location']?.toString().trim().isEmpty ?? true) 
        ? 'Multiple Locations / N/A' 
        : assignment['pickup_location'];
    
    final delivery = (assignment['delivery_location']?.toString().trim().isEmpty ?? true) 
        ? 'Multiple Locations / N/A' 
        : assignment['delivery_location'];

    final double agreedAmount = double.tryParse(assignment['total_agreed_amount']?.toString() ?? '0') ?? 0.0;
    final double paidAmount = double.tryParse(assignment['total_paid_amount']?.toString() ?? '0') ?? 0.0;
    final double balanceAmount = agreedAmount - paidAmount;
    final String txId = assignment['vendor_transaction_id']?.toString().trim() ?? '';

    final courierStatus   = (assignment['courier_status'] ?? '').toString().toLowerCase();
    final isDelivered     = courierStatus.contains('delivered');
    final driverAccepted  = courierStatus.isNotEmpty &&
        courierStatus != 'active' &&
        courierStatus != 'pending' &&
        courierStatus != 'assigned' &&
        !courierStatus.contains('pickup assigned');
    final refNumbers      = (assignment['reference_numbers'] ?? '').toString().trim();
    final trackingNumbers = (assignment['tracking_numbers']  ?? '').toString().trim();

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      elevation: 4,
      shadowColor: Colors.black.withOpacity(0.1),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Theme(
          data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
          child: ExpansionTile(
            backgroundColor: Colors.white,
            collapsedBackgroundColor: Colors.white,
            tilePadding: const EdgeInsets.all(16),
            title: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(statusIcon, color: statusColor, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'AL: ${assignment['al_number']}',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Vehicle: ${assignment['vehicle_number']} - ${assignment['vehicle_name']}',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            subtitle: Padding(
              padding: const EdgeInsets.only(top: 8.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    _formatDate(assignment['assignment_date']),
                    style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: isDelivered ? Colors.green : statusColor,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      isDelivered ? 'DELIVERED' : status.toUpperCase(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Divider(),
                    const SizedBox(height: 12),
                    _buildDetailRow(Icons.person_rounded, 'Driver', assignment['driver_name'] ?? 'N/A'),
                    const SizedBox(height: 10),
                    // Courier status chip
                    if (courierStatus.isNotEmpty) ...[
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: isDelivered ? Colors.green.withOpacity(0.1) : Colors.blue.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              isDelivered ? Icons.local_shipping_rounded : Icons.info_outline_rounded,
                              size: 18,
                              color: isDelivered ? Colors.green : Colors.blue,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Shipment Status', style: TextStyle(fontSize: 11, color: Colors.grey[600], fontWeight: FontWeight.w600, letterSpacing: 0.3)),
                                const SizedBox(height: 4),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: isDelivered ? Colors.green.withOpacity(0.1) : Colors.blue.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Text(
                                    (assignment['courier_status'] ?? '').toString().toUpperCase(),
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: isDelivered ? Colors.green.shade700 : Colors.blue.shade700,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                    ],
                    const SizedBox(height: 6),
                    _buildLocationRow(Icons.location_on_rounded, 'Pickup Location', pickup, const Color(0xFF10B981)),
                    const SizedBox(height: 12),
                    _buildLocationRow(Icons.flag_rounded, 'Delivery Location', delivery, const Color(0xFFEF4444)),
                    
                    if (assignment['notes'] != null && assignment['notes'].toString().trim().isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.amber.withOpacity(0.05),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.amber.withOpacity(0.2)),
                        ),
                        child: _buildDetailRow(Icons.note_rounded, 'Notes', assignment['notes'], Colors.amber[700]!),
                      ),
                    ],

                    // Payment Summary Block
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey[200]!),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.02),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          )
                        ],
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.payments_rounded, color: AppTheme.primaryBlue, size: 20),
                              const SizedBox(width: 8),
                              const Text(
                                'Payment Details',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14,
                                  color: AppTheme.primaryBlue,
                                ),
                              ),
                            ],
                          ),
                          const Divider(height: 24),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              _buildPaymentStat('Total Amount', agreedAmount, Colors.grey[800]!),
                              _buildPaymentStat('Amount Paid', paidAmount, Colors.green),
                              _buildPaymentStat('Balance', balanceAmount, balanceAmount > 0 ? Colors.red : Colors.grey[800]!),
                            ],
                          ),
                          if (txId.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                              decoration: BoxDecoration(
                                color: Colors.blue.withOpacity(0.05),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: Colors.blue.withOpacity(0.1)),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.receipt_long, size: 14, color: AppTheme.primaryBlue),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Transactions: $txId',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w600,
                                        color: AppTheme.primaryBlue,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),

                    // View Shipment History button — always visible for all assignments
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: OutlinedButton.icon(
                        onPressed: () => _showShipmentHistory(assignment),
                        icon: const Icon(Icons.history_rounded, size: 18),
                        label: const Text('View Payment History', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.primaryBlue,
                          side: const BorderSide(color: AppTheme.primaryBlue),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),

                    // Show delivered banner OR live-tracking section
                    if (isDelivered) ...[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.green.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.green.withOpacity(0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle_rounded, color: Colors.green, size: 22),
                            const SizedBox(width: 10),
                            const Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('Shipment Delivered', style: TextStyle(color: Colors.green, fontWeight: FontWeight.w700, fontSize: 14)),
                                  SizedBox(height: 2),
                                  Text('This order has been successfully delivered.', style: TextStyle(color: Colors.green, fontSize: 12)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ] else if (status.toLowerCase() != 'completed' && status.toLowerCase() != 'cancelled') ...[
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton.icon(
                          onPressed: () => _trackDriver(assignment),
                          icon: const Icon(Icons.my_location_rounded),
                          label: const Text(
                            'Track Driver Live Location',
                            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF4285F4),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            elevation: 3,
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      if (!driverAccepted) ...[
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
                          decoration: BoxDecoration(
                            color: Colors.orange.withOpacity(0.08),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.orange.withOpacity(0.3)),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.hourglass_top_rounded, color: Colors.orange[700], size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Waiting for driver to accept this order',
                                  style: TextStyle(
                                    color: Colors.orange[800],
                                    fontWeight: FontWeight.w600,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ] else ...[
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
                          decoration: BoxDecoration(
                            color: Colors.green.withOpacity(0.08),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.green.withOpacity(0.3)),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.check_circle, color: Colors.green[700], size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Driver accepted — Order is in progress',
                                  style: TextStyle(
                                    color: Colors.green[800],
                                    fontWeight: FontWeight.w600,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ]
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(IconData icon, String label, String value, [Color? iconColor]) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: (iconColor ?? AppTheme.primaryBlue).withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(
            icon,
            size: 18,
            color: iconColor ?? AppTheme.primaryBlue,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  color: Colors.grey[600],
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.3,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 14,
                  color: AppTheme.textPrimary,
                  fontWeight: FontWeight.w600,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLocationRow(IconData icon, String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.05),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: color.withOpacity(0.2),
          width: 1.5,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(8),
              boxShadow: [
                BoxShadow(
                  color: color.withOpacity(0.3),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Icon(icon, size: 16, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 11,
                    color: color,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppTheme.textPrimary,
                    fontWeight: FontWeight.w600,
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

  Widget _buildPaymentStat(String label, double amount, Color color) {
    return Column(
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            color: Colors.grey[600],
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          '₹${amount.toStringAsFixed(2)}',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: color,
            fontFamily: 'monospace',
          ),
        ),
      ],
    );
  }

  Color _getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'active':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'completed':
        return Colors.blue;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  IconData _getStatusIcon(String status) {
    switch (status.toLowerCase()) {
      case 'active':
        return Icons.local_shipping;
      case 'pending':
        return Icons.schedule;
      case 'completed':
        return Icons.check_circle;
      case 'cancelled':
        return Icons.cancel;
      default:
        return Icons.info;
    }
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return 'N/A';
    try {
      final date = DateTime.parse(dateStr);
      return DateFormat('dd MMM yyyy, hh:mm a').format(date);
    } catch (e) {
      return dateStr;
    }
  }

  // ── SHIPMENT HISTORY — step-by-step tracking timeline ────────────────────
  void _showShipmentHistory(Map<String, dynamic> assignment) {
    final trackingRaw = (assignment['tracking_numbers'] ?? assignment['al_number'] ?? '').toString();
    final tracking    = trackingRaw.split(',').first.trim();
    final txId        = assignment['vendor_transaction_id']?.toString().trim() ?? '';
    final alNo        = assignment['al_number']?.toString() ?? '';
    final agreed      = double.tryParse(assignment['total_agreed_amount']?.toString() ?? '0') ?? 0.0;
    final paid        = double.tryParse(assignment['total_paid_amount']?.toString()   ?? '0') ?? 0.0;

    if (tracking.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No tracking number found for this assignment.')),
      );
      return;
    }

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (ctx) => _ShipmentHistorySheet(
        tracking: tracking,
        alNumber: alNo,
        txId: txId,
        agreedAmount: agreed,
        paidAmount: paid,
      ),
    );
  }


  // ── TRACK DRIVER — fetch live location and show options ─────────────────
  Future<void> _trackDriver(Map<String, dynamic> assignment) async {
    final vehicleId = int.tryParse(assignment['vehicle_id']?.toString() ?? '0') ?? 0;
    if (vehicleId <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No vehicle linked to this assignment.')),
      );
      return;
    }

    // Show loading
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(
        child: Card(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(height: 16),
                Text('Fetching driver location...'),
              ],
            ),
          ),
        ),
      ),
    );

    final result = await ApiService.getDriverLocation(vehicleId: vehicleId);
    if (!mounted) return;
    Navigator.of(context, rootNavigator: true).pop(); // close loading

    if (result['status'] != 'success') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Failed to fetch location'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final bool hasLocation = result['has_location'] == true;
    final bool isOnline = result['is_online'] == true;
    final double? lat = result['latitude'] != null ? double.tryParse(result['latitude'].toString()) : null;
    final double? lng = result['longitude'] != null ? double.tryParse(result['longitude'].toString()) : null;
    final String driverName = result['driver_name'] ?? assignment['driver_name'] ?? 'Driver';
    final String vehicleNo = result['vehicle_number'] ?? assignment['vehicle_number'] ?? '';
    final String lastUpdated = result['last_updated'] ?? '';
    final String address = result['address'] ?? '';

    // Format last updated time
    String updatedText = 'Unknown';
    if (lastUpdated.isNotEmpty) {
      try {
        final dt = DateTime.parse(lastUpdated).toLocal();
        final diff = DateTime.now().difference(dt);
        if (diff.inMinutes < 1) {
          updatedText = 'Just now';
        } else if (diff.inMinutes < 60) {
          updatedText = '${diff.inMinutes} min ago';
        } else if (diff.inHours < 24) {
          updatedText = '${diff.inHours} hr ago';
        } else {
          updatedText = DateFormat('dd MMM, hh:mm a').format(dt);
        }
      } catch (_) {
        updatedText = lastUpdated;
      }
    }

    // Show location bottom sheet
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.only(bottom: 20),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),

            // Header
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
                    ),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(Icons.location_on_rounded, color: Colors.white, size: 24),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Driver Live Location',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                      Text(
                        '$driverName · $vehicleNo',
                        style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                      ),
                    ],
                  ),
                ),
                // Online indicator
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: (isOnline ? Colors.green : Colors.grey).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isOnline ? Colors.green : Colors.grey,
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 7, height: 7,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: isOnline ? Colors.green : Colors.grey,
                        ),
                      ),
                      const SizedBox(width: 5),
                      Text(
                        isOnline ? 'Online' : 'Offline',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: isOnline ? Colors.green : Colors.grey,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 20),

            if (hasLocation && lat != null && lng != null) ...[
              // Location info card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.grey[50],
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.grey[200]!),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Icon(Icons.my_location_rounded, size: 16, color: AppTheme.primaryBlue),
                        const SizedBox(width: 8),
                        Text(
                          'Coordinates',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: FontWeight.w600),
                        ),
                        const Spacer(),
                        Text(
                          '${lat.toStringAsFixed(5)}, ${lng.toStringAsFixed(5)}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textPrimary,
                            fontFamily: 'monospace',
                          ),
                        ),
                      ],
                    ),
                    if (address.isNotEmpty) ...[
                      const Divider(height: 16),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.place_rounded, size: 16, color: Colors.red[400]),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              address,
                              style: TextStyle(fontSize: 12, color: Colors.grey[700]),
                            ),
                          ),
                        ],
                      ),
                    ],
                    const Divider(height: 16),
                    Row(
                      children: [
                        Icon(Icons.access_time_rounded, size: 16, color: Colors.orange[400]),
                        const SizedBox(width: 8),
                        Text(
                          'Last updated: $updatedText',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Open in Google Maps button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    final url = 'https://www.google.com/maps/search/?api=1&query=$lat,$lng';
                    final uri = Uri.parse(url);
                    if (await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    } else {
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Could not open Google Maps')),
                        );
                      }
                    }
                  },
                  icon: const Icon(Icons.map_rounded),
                  label: const Text(
                    'Open in Google Maps',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF4285F4), // Google blue
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 4,
                  ),
                ),
              ),

              const SizedBox(height: 10),

              // Refresh location button
              SizedBox(
                width: double.infinity,
                height: 48,
                child: OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(ctx);
                    _trackDriver(assignment); // Re-fetch
                  },
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Refresh Location'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppTheme.primaryBlue,
                    side: const BorderSide(color: AppTheme.primaryBlue),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
            ] else ...[
              // No location yet
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.05),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.orange.withOpacity(0.2)),
                ),
                child: Column(
                  children: [
                    Icon(Icons.location_off_rounded, color: Colors.orange[400], size: 48),
                    const SizedBox(height: 12),
                    const Text(
                      'No location data yet',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'The driver\'s location will appear here once they go online and start moving.',
                      style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(ctx);
                        _trackDriver(assignment);
                      },
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Try Again'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.orange,
                        side: const BorderSide(color: Colors.orange),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }
}

// ── Payment History Bottom Sheet ─────────────────────────────────────────
class _ShipmentHistorySheet extends StatefulWidget {
  final String tracking;
  final String alNumber;
  final String txId;
  final double agreedAmount;
  final double paidAmount;
  const _ShipmentHistorySheet({
    required this.tracking,
    required this.alNumber,
    this.txId = '',
    this.agreedAmount = 0,
    this.paidAmount = 0,
  });

  @override
  State<_ShipmentHistorySheet> createState() => _ShipmentHistorySheetState();
}

class _ShipmentHistorySheetState extends State<_ShipmentHistorySheet> {
  bool _loading = true;
  List<Map<String, dynamic>> _installments = [];
  double _livePaid = 0;
  double _liveAgreed = 0;

  @override
  void initState() {
    super.initState();
    _liveAgreed = widget.agreedAmount;
    _livePaid   = widget.paidAmount;
    _fetchInstallments();
  }

  Future<void> _fetchInstallments() async {
    if (!mounted) return;
    try {
      final result = await ApiService.getShipmentHistory(tracking: widget.tracking);
      if (!mounted) return;
      final paymentMap = result['payment'] as Map<String, dynamic>? ?? {};
      final rawList    = result['payments'] as List<dynamic>? ?? [];
      setState(() {
        _installments = rawList.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        // Prefer server-calculated totals if available
        final serverAgreed = double.tryParse(paymentMap['agreed_amount']?.toString() ?? '');
        final serverPaid   = double.tryParse(paymentMap['paid_amount']?.toString()   ?? '');
        if (serverAgreed != null && serverAgreed > 0) _liveAgreed = serverAgreed;
        if (serverPaid   != null)                     _livePaid   = serverPaid;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Widget _row(IconData icon, String label, String value, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, size: 16, color: color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[500], fontWeight: FontWeight.w600)),
                const SizedBox(height: 3),
                Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF1A1A2E))),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final balance    = _liveAgreed - _livePaid;
    final isPaidFull = balance <= 0 && _livePaid > 0;
    final pctPaid    = _liveAgreed > 0
        ? ((_livePaid / _liveAgreed) * 100).clamp(0, 100).toStringAsFixed(0)
        : '0';

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle
              Center(
                child: Container(
                  width: 40, height: 4,
                  decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)),
                ),
              ),
              const SizedBox(height: 16),

              // Header
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)]),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.account_balance_wallet_rounded, color: Colors.white, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Payment History', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0D2E6E))),
                        if (widget.alNumber.isNotEmpty)
                          Text('AL: ${widget.alNumber}', style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                    color: Colors.grey,
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Payment status badge
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: isPaidFull ? Colors.green.withOpacity(0.1) : Colors.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(30),
                  border: Border.all(color: isPaidFull ? Colors.green.withOpacity(0.3) : Colors.orange.withOpacity(0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(isPaidFull ? Icons.check_circle_rounded : Icons.pending_rounded,
                        size: 16, color: isPaidFull ? Colors.green : Colors.orange[700]),
                    const SizedBox(width: 6),
                    Text(
                      isPaidFull ? 'Fully Paid' : 'Partially Paid ($pctPaid%)',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: isPaidFull ? Colors.green[700] : Colors.orange[800],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Amount summary cards
              Row(
                children: [
                  _amountCard('Total Amount', _liveAgreed, const Color(0xFF0D2E6E), Icons.receipt_long_rounded),
                  const SizedBox(width: 10),
                  _amountCard('Amount Paid', _livePaid, Colors.green, Icons.check_circle_rounded),
                  const SizedBox(width: 10),
                  _amountCard('Balance Due', balance, balance > 0 ? Colors.red : Colors.grey, Icons.account_balance_rounded),
                ],
              ),
              const SizedBox(height: 16),

              // Progress bar
              if (_liveAgreed > 0) ...[
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Payment Progress', style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: FontWeight.w600)),
                    Text('$pctPaid% paid', style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: FontWeight.w700)),
                  ],
                ),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: (_livePaid / _liveAgreed).clamp(0.0, 1.0),
                    backgroundColor: Colors.red[100],
                    valueColor: const AlwaysStoppedAnimation<Color>(Colors.green),
                    minHeight: 10,
                  ),
                ),
                const SizedBox(height: 20),
              ],

              // ── Payment Installments ───────────────────────────────────
              const Divider(),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Icon(Icons.payments_rounded, size: 16, color: Color(0xFF0D2E6E)),
                  const SizedBox(width: 8),
                  Text(
                    'Payment Installments',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Colors.grey[800]),
                  ),
                  if (!_loading && _installments.isNotEmpty) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0D2E6E).withOpacity(0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${_installments.length}',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF0D2E6E)),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 12),

              if (_loading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: CircularProgressIndicator(color: Color(0xFF0D2E6E)),
                  ),
                )
              else if (_installments.isEmpty)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.grey[50],
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey[200]!),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline, color: Colors.grey[400], size: 18),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'No payment records found yet.',
                          style: TextStyle(color: Colors.grey[500], fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                )
              else
                Column(
                  children: _installments.asMap().entries.map((entry) {
                    final idx = entry.key;
                    final p   = entry.value;
                    final amt = double.tryParse(p['amount']?.toString() ?? '0') ?? 0.0;
                    final tx  = p['transaction_id']?.toString() ?? '';
                    final dt  = p['paid_at']?.toString() ?? '';
                    final notes = p['notes']?.toString() ?? '';
                    final paidBy = p['paid_by']?.toString() ?? '';

                    // Format date nicely
                    String dateStr = dt;
                    try {
                      if (dt.isNotEmpty) {
                        final parsed = DateTime.parse(dt);
                        dateStr = DateFormat('dd MMM yyyy, hh:mm a').format(parsed);
                      }
                    } catch (_) {}

                    final isLast = idx == _installments.length - 1;

                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: Colors.green.withOpacity(0.3)),
                        boxShadow: [
                          BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2)),
                        ],
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Row(
                            children: [
                              // Installment number circle
                              Container(
                                width: 30, height: 30,
                                decoration: BoxDecoration(
                                  color: Colors.green.withOpacity(0.1),
                                  shape: BoxShape.circle,
                                ),
                                child: Center(
                                  child: Text(
                                    '${idx + 1}',
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Colors.green),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Installment ${idx + 1}',
                                      style: TextStyle(fontSize: 11, color: Colors.grey[500], fontWeight: FontWeight.w600),
                                    ),
                                    if (dateStr.isNotEmpty)
                                      Text(dateStr, style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                                  ],
                                ),
                              ),
                              Text(
                                '₹${amt.toStringAsFixed(0)}',
                                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Colors.green),
                              ),
                            ],
                          ),
                          if (tx.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              decoration: BoxDecoration(
                                color: const Color(0xFF0D2E6E).withOpacity(0.05),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.receipt_rounded, size: 13, color: Color(0xFF0D2E6E)),
                                  const SizedBox(width: 6),
                                  Text('Transaction ID: ', style: TextStyle(fontSize: 11, color: Colors.grey[500], fontWeight: FontWeight.w600)),
                                  Expanded(
                                    child: Text(
                                      tx,
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF0D2E6E)),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          if (notes.isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Icon(Icons.notes_rounded, size: 13, color: Colors.grey[400]),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    notes,
                                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          if (paidBy.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Icon(Icons.person_outline_rounded, size: 13, color: Colors.grey[400]),
                                const SizedBox(width: 6),
                                Text(
                                  'By: $paidBy',
                                  style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    );
                  }).toList(),
                ),

              // Fleet Assignment row
              if (!_loading) ...[
                const SizedBox(height: 4),
                _row(Icons.local_shipping_outlined, 'Fleet Assignment',
                    widget.alNumber.isNotEmpty ? widget.alNumber : '—', Colors.orange),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _amountCard(String label, double amount, Color color, IconData icon) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
        decoration: BoxDecoration(
          color: color.withOpacity(0.06),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withOpacity(0.2)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 18, color: color),
            const SizedBox(height: 6),
            Text(label, style: TextStyle(fontSize: 9, color: Colors.grey[600], fontWeight: FontWeight.w600), textAlign: TextAlign.center),
            const SizedBox(height: 4),
            Text('₹${amount.toStringAsFixed(0)}', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: color)),
          ],
        ),
      ),
    );
  }
}
