import 'package:flutter/material.dart';
import 'dart:async';
import 'dart:io';
import '../../services/api_service.dart';
import '../../config/app_theme.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:geolocator/geolocator.dart';

class DriverOrdersComplete extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverOrdersComplete({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverOrdersComplete> createState() => _DriverOrdersCompleteState();
}

class _DriverOrdersCompleteState extends State<DriverOrdersComplete> {
  List<Map<String, dynamic>> _allOrders = [];
  bool _isLoading = true;
  Timer? _refreshTimer;
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _loadOrders();
    _startAutoRefresh();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(const Duration(seconds: 20), (timer) {
      if (mounted) {
        _loadOrders();
      }
    });
  }

  Future<void> _loadOrders() async {
    try {
      final vehicleIdRaw = widget.driverData['driver']?['vehicle_id'];
      int vehicleId = 0;
      if (vehicleIdRaw != null) {
        vehicleId = vehicleIdRaw is int ? vehicleIdRaw : int.tryParse(vehicleIdRaw.toString()) ?? 0;
      }
      final result = await ApiService.getDriverOrdersEnhanced(vehicleId: vehicleId);

      if (result['status'] == 'success' && mounted) {
        setState(() {
          _allOrders = List<Map<String, dynamic>>.from(result['orders'] ?? []);
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Error loading orders: $e');
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  List<Map<String, String>> _getAvailableStatuses(String currentStatus) {
    final status = currentStatus.toLowerCase();
    
    if (status == 'assigned' || status == 'awb created' || status == 'pickup assigned' || status == 'pickup_assigned') {
      return [
        {'value': 'Picked Up', 'label': 'Picked Up', 'icon': 'check_circle'},
      ];
    } else if (status == 'picked up' || status == 'picked_up') {
      return [
        {'value': 'In Scan', 'label': 'In Scan', 'icon': 'qr_code_scanner'},
        {'value': 'In Transit', 'label': 'In Transit', 'icon': 'local_shipping'},
      ];
    } else if (status == 'in scan' || status == 'in_scan') {
      return [
        {'value': 'In Transit', 'label': 'In Transit', 'icon': 'local_shipping'},
      ];
    } else if (status == 'in transit' || status == 'in_transit') {
      return [
        {'value': 'In Warehouse', 'label': 'In Warehouse', 'icon': 'warehouse'},
        {'value': 'Out for Delivery', 'label': 'Out for Delivery', 'icon': 'delivery_dining'},
      ];
    } else if (status == 'in warehouse' || status == 'in_warehouse') {
      return [
        {'value': 'Out for Delivery', 'label': 'Out for Delivery', 'icon': 'delivery_dining'},
      ];
    } else if (status == 'out for delivery' || status == 'out_for_delivery') {
      return [
        {'value': 'Delivered', 'label': 'Delivered', 'icon': 'check_circle'},
      ];
    }
    
    return [];
  }

  bool _requiresPOD(String newStatus) {
    final status = newStatus.toLowerCase();
    return status == 'picked up' || status == 'picked_up' || status == 'out for delivery' || status == 'out_for_delivery' || status == 'delivered';
  }

  Color _getLoadCategoryColor(String category) {
    switch (category) {
      case 'express':
        return Colors.red;
      case 'ftl':
        return AppTheme.accentPurple;
      case 'part_load':
      default:
        return AppTheme.primaryBlue;
    }
  }

  String _getLoadCategoryLabel(String category) {
    switch (category) {
      case 'express':
        return 'Express';
      case 'ftl':
        return 'FTL';
      case 'part_load':
      default:
        return 'Part Load (PTL)';
    }
  }

  IconData _getStatusIcon(String status) {
    switch (status.toLowerCase()) {
      case 'assigned':
      case 'awb created':
      case 'pickup assigned':
      case 'pickup_assigned':
        return Icons.assignment_rounded;
      case 'picked up':
      case 'picked_up':
        return Icons.check_circle_rounded;
      case 'in scan':
      case 'in_scan':
        return Icons.qr_code_scanner_rounded;
      case 'in transit':
      case 'in_transit':
        return Icons.local_shipping_rounded;
      case 'in warehouse':
      case 'in_warehouse':
        return Icons.warehouse_rounded;
      case 'out for delivery':
      case 'out_for_delivery':
        return Icons.delivery_dining_rounded;
      case 'delivered':
        return Icons.check_circle_rounded;
      default:
        return Icons.info_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    // If it's explicitly 'ftl' or 'FULL LOAD', it goes to FTL. Everything else goes to Part Load.
    final ftlOrders = _allOrders.where((order) {
      final category = (order['load_category'] ?? '').toString().toLowerCase();
      final method = (order['shipping_method'] ?? '').toString().toLowerCase();
      return category == 'ftl' || method == 'full load';
    }).toList();

    final partLoadOrders = _allOrders.where((order) {
      final category = (order['load_category'] ?? '').toString().toLowerCase();
      final method = (order['shipping_method'] ?? '').toString().toLowerCase();
      return category != 'ftl' && method != 'full load';
    }).toList();

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AppTheme.backgroundLight,
        appBar: AppBar(
          title: const Text('My Orders'),
          backgroundColor: AppTheme.primaryBlue,
          elevation: 0,
          foregroundColor: Colors.white,
          bottom: const TabBar(
            indicatorColor: Colors.white,
            indicatorWeight: 4,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white60,
            labelStyle: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            unselectedLabelStyle: TextStyle(fontWeight: FontWeight.w500, fontSize: 15),
            tabs: [
              Tab(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.inventory_2_rounded, size: 20),
                    SizedBox(width: 8),
                    Text('Part Load'),
                  ],
                ),
              ),
              Tab(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.local_shipping_rounded, size: 20),
                    SizedBox(width: 8),
                    Text('Full Load'),
                  ],
                ),
              ),
            ],
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh_rounded),
              onPressed: _loadOrders,
            ),
          ],
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  _buildOrdersList(partLoadOrders),
                  _buildOrdersList(ftlOrders),
                ],
              ),
      ),
    );
  }

  Widget _buildOrdersList(List<Map<String, dynamic>> orders) {
    if (orders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: AppTheme.primaryBlue.withOpacity(0.05),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.assignment_outlined, size: 80, color: AppTheme.primaryBlue.withOpacity(0.5)),
            ),
            const SizedBox(height: 24),
            Text('No Assigned Orders', style: AppTheme.heading2.copyWith(color: AppTheme.primaryBlue)),
            const SizedBox(height: 8),
            Text('You currently have no active orders in this category.', style: AppTheme.bodyMedium.copyWith(color: Colors.grey[600]), textAlign: TextAlign.center),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadOrders,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: orders.length,
        itemBuilder: (context, index) {
          return _buildOrderCard(orders[index]);
        },
      ),
    );
  }

  Widget _buildOrderCard(Map<String, dynamic> order) {
    final alNumber = order['al_number'] ?? 'N/A';
    final loadCategory = order['load_category'] ?? 'part_load';
    final status = order['status'] ?? 'Unknown';
    final categoryColor = _getLoadCategoryColor(loadCategory);

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: AppTheme.cardShadow,
        border: Border.all(color: categoryColor.withOpacity(0.3)),
      ),
      child: InkWell(
        onTap: () => _showOrderDetails(order),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: AppTheme.accentOrange.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      children: [
                        Icon(_getStatusIcon(status), size: 14, color: AppTheme.accentOrange),
                        const SizedBox(width: 4),
                        Text(
                          status,
                          style: TextStyle(
                            color: AppTheme.accentOrange,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.receipt_long_rounded, size: 20, color: categoryColor),
                  const SizedBox(width: 8),
                  Text(
                    'AL: $alNumber',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Tracking: ${order['tracking_number'] ?? 'N/A'}',
                style: AppTheme.caption,
              ),
              const Divider(height: 24),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on, size: 16, color: AppTheme.accentGreen),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      order['sender_address'] ?? 'N/A',
                      style: AppTheme.caption,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.map, size: 20, color: AppTheme.primaryBlue),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    onPressed: () => _openMap(order['sender_address']),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on, size: 16, color: Colors.red),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      order['receiver_address'] ?? 'N/A',
                      style: AppTheme.caption,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.map, size: 20, color: AppTheme.primaryBlue),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    onPressed: () => _openMap(order['receiver_address']),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  const SizedBox(), // Payment hidden from driver

                  ElevatedButton(
                    onPressed: () => _showOrderDetails(order),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: categoryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    ),
                    child: const Text('Update Status'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showOrderDetails(Map<String, dynamic> order) {
    final availableStatuses = _getAvailableStatuses(order['status'] ?? '');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => DraggableScrollableSheet(
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
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              Expanded(
                child: ListView(
                  controller: controller,
                  padding: const EdgeInsets.all(20),
                  children: [
                    Text('Order Details', style: AppTheme.heading2),
                    const SizedBox(height: 20),
                    _buildDetailRow('AL Number', order['al_number'] ?? 'N/A'),
                    _buildDetailRow('Tracking Number', order['tracking_number'] ?? 'N/A'),
                    _buildDetailRow('Load Type', _getLoadCategoryLabel(order['load_category'] ?? 'part_load')),
                    _buildDetailRow('Status', order['status'] ?? 'N/A'),
                    _buildDetailRow('Customer', order['customer_name'] ?? 'N/A'),
                    _buildDetailRow('Sender', order['sender_name'] ?? 'N/A'),
                    _buildDetailRow('Sender Phone', order['sender_mobile'] ?? 'N/A'),
                    _buildDetailRow('Sender Address', order['sender_address'] ?? 'N/A'),
                    _buildDetailRow('Receiver', order['receiver_name'] ?? 'N/A'),
                    _buildDetailRow('Receiver Phone', order['receiver_mobile'] ?? 'N/A'),
                    _buildDetailRow('Receiver Address', order['receiver_address'] ?? 'N/A'),
                    const SizedBox(height: 20),
                    if (availableStatuses.isNotEmpty) ...[
                      Text('Update Status:', style: AppTheme.bodyLarge.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 12),
                      ...availableStatuses.map((statusOption) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: ElevatedButton.icon(
                              onPressed: () => _handleStatusUpdate(order, statusOption['value']!),
                              icon: Icon(_getIconFromString(statusOption['icon']!)),
                              label: Text(statusOption['label']!),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppTheme.primaryBlue,
                                foregroundColor: Colors.white,
                              ),
                            ),
                          ),
                        );
                      }).toList(),
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

  IconData _getIconFromString(String iconName) {
    switch (iconName) {
      case 'check_circle':
        return Icons.check_circle_rounded;
      case 'qr_code_scanner':
        return Icons.qr_code_scanner_rounded;
      case 'local_shipping':
        return Icons.local_shipping_rounded;
      case 'warehouse':
        return Icons.warehouse_rounded;
      case 'delivery_dining':
        return Icons.delivery_dining_rounded;
      default:
        return Icons.update_rounded;
    }
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: AppTheme.bodyMedium.copyWith(
                color: AppTheme.textSecondary,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTheme.bodyMedium.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openMap(String? address) async {
    if (address == null || address.isEmpty || address == 'N/A') return;
    final url = Uri.parse('https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(address)}');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open map'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _handleStatusUpdate(Map<String, dynamic> order, String newStatus) async {
    Navigator.pop(context);

    if (_requiresPOD(newStatus)) {
      // Show POD upload dialog
      await _showPODUploadDialog(order, newStatus);
    } else {
      // Update status directly
      await _updateStatus(order, newStatus, null);
    }
  }

  Future<void> _showPODUploadDialog(Map<String, dynamic> order, String newStatus) async {
    final podType = newStatus.toLowerCase() == 'picked up' || newStatus.toLowerCase() == 'picked_up' ? 'pickup' : 'delivery';
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Upload POD - ${newStatus}'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Please upload proof of ${podType}'),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                ElevatedButton.icon(
                  onPressed: () async {
                    Navigator.pop(context);
                    await _takePODPhoto(order, newStatus, podType, ImageSource.camera);
                  },
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Camera'),
                ),
                ElevatedButton.icon(
                  onPressed: () async {
                    Navigator.pop(context);
                    await _takePODPhoto(order, newStatus, podType, ImageSource.gallery);
                  },
                  icon: const Icon(Icons.photo_library),
                  label: const Text('Gallery'),
                ),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
        ],
      ),
    );
  }

  Future<void> _takePODPhoto(Map<String, dynamic> order, String newStatus, String podType, ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(source: source, imageQuality: 70);
      
      if (image != null) {
        // Show loading
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) => const Center(child: CircularProgressIndicator()),
        );

        final vehicleIdRaw = widget.driverData['driver']?['vehicle_id'];
        int vehicleId = 0;
        if (vehicleIdRaw != null) {
          vehicleId = vehicleIdRaw is int ? vehicleIdRaw : int.tryParse(vehicleIdRaw.toString()) ?? 0;
        }

        // Get Live Location
        Position? position;
        try {
          bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
          if (serviceEnabled) {
            LocationPermission permission = await Geolocator.checkPermission();
            if (permission == LocationPermission.denied) {
              permission = await Geolocator.requestPermission();
            }
            if (permission == LocationPermission.whileInUse || permission == LocationPermission.always) {
              position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
            }
          }
        } catch(e) {
          print('Could not get location: $e');
        }

        final result = await ApiService.uploadPOD(
          alNumber: order['al_number'],
          vehicleId: vehicleId,
          podType: podType,
          imageFile: image,
          latitude: position?.latitude,
          longitude: position?.longitude,
        );

        Navigator.pop(context); // Close loading

        if (result['status'] == 'success') {
          // Update status after successful POD upload
          await _updateStatus(order, newStatus, null);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message'] ?? 'Failed to upload POD'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      print('Error taking POD photo: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _updateStatus(Map<String, dynamic> order, String newStatus, String? notes) async {
    final vehicleIdRaw = widget.driverData['driver']?['vehicle_id'];
    int vehicleId = 0;
    if (vehicleIdRaw != null) {
      vehicleId = vehicleIdRaw is int ? vehicleIdRaw : int.tryParse(vehicleIdRaw.toString()) ?? 0;
    }

    final result = await ApiService.updateOrderStatusEnhanced(
      alNumber: order['al_number'],
      vehicleId: vehicleId,
      status: newStatus,
      driverNotes: notes,
    );

    if (result['status'] == 'success') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Order status updated to $newStatus'),
          backgroundColor: AppTheme.accentGreen,
        ),
      );
      _loadOrders();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Failed to update status'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
}
