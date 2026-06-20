// lib/screens/driver/proof_of_delivery_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:image_picker/image_picker.dart';
import 'dart:async';
import 'dart:typed_data';
import 'package:geolocator/geolocator.dart';
import '../../services/api_service.dart';
import '../../services/permission_service.dart';
import '../../config/app_theme.dart';
import 'barcode_scanner_screen.dart';

class ProofOfDeliveryScreen extends StatefulWidget {
  final Map<String, dynamic> order;
  final Map<String, dynamic> driverData;
  final String podType; // 'pickup' or 'delivery'

  const ProofOfDeliveryScreen({
    super.key,
    required this.order,
    required this.driverData,
    required this.podType,
  });

  @override
  State<ProofOfDeliveryScreen> createState() => _ProofOfDeliveryScreenState();
}

class _ProofOfDeliveryScreenState extends State<ProofOfDeliveryScreen> {
  final TextEditingController _receiverNameController = TextEditingController();
  final TextEditingController _receiverPhoneController = TextEditingController();
  String? _scannedBarcode;

  // Use XFile — works on both web and mobile
  XFile? _proofPhoto;
  Uint8List? _photoBytes; // for web preview
  bool _isLoading = false;
  final ImagePicker _picker = ImagePicker();

  bool get _isDelivery => widget.podType == 'delivery';
  Color get _accent => _isDelivery ? AppTheme.accentGreen : AppTheme.accentOrange;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _receiverNameController.text = widget.order['receiver_name'] ?? '';
        _receiverPhoneController.text = widget.order['receiver_mobile'] ?? '';
      }
    });
  }

  @override
  void dispose() {
    _receiverNameController.dispose();
    _receiverPhoneController.dispose();
    super.dispose();
  }

  // ── PICK IMAGE — works on web and mobile ────────────────────────────────
  Future<void> _pickImage(ImageSource source) async {
    // Request permission before accessing camera or gallery
    if (source == ImageSource.camera) {
      final granted = await PermissionService.requestCamera(context);
      if (!granted || !mounted) return;
    } else {
      final granted = await PermissionService.requestStorage(context);
      if (!granted || !mounted) return;
    }

    try {
      setState(() => _isLoading = true);

      final XFile? image = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1920,
        maxHeight: 1920,
      );

      if (!mounted) return;

      if (image != null) {
        // Read bytes immediately — works on all platforms
        final bytes = await image.readAsBytes();
        setState(() {
          _proofPhoto = image;
          _photoBytes = bytes;
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('✅ Photo selected successfully'),
            backgroundColor: Colors.green,
            duration: Duration(seconds: 2),
          ),
        );
      } else {
        setState(() => _isLoading = false);
      }
    } on PlatformException catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      String msg = 'Could not access photo.';
      if (e.code == 'camera_access_denied') msg = 'Camera permission denied. Enable in Settings.';
      if (e.code == 'photo_access_denied') msg = 'Gallery permission denied. Enable in Settings.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: Colors.red, duration: const Duration(seconds: 4)),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    }
  }

  // ── SCAN BARCODE ─────────────────────────────────────────────────────────
  Future<void> _scanBarcode() async {
    setState(() => _isLoading = true);
    try {
      final String? barcode = await Navigator.push<String>(
        context,
        MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
      );
      if (!mounted) return;
      if (barcode != null && barcode.isNotEmpty) {
        setState(() => _scannedBarcode = barcode);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('✅ Barcode: $barcode')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Scan error: $e')),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // ── GET LOCATION ─────────────────────────────────────────────────────────
  Future<Position?> _getCurrentLocation() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Please enable location services.'), backgroundColor: Colors.orange),
          );
        }
        return null;
      }
      LocationPermission perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        perm = await Geolocator.requestPermission();
        if (perm == LocationPermission.denied) return null;
      }
      if (perm == LocationPermission.deniedForever) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Location permanently denied. Enable in Settings.'), backgroundColor: Colors.red),
          );
        }
        return null;
      }
      return await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high)
          .timeout(const Duration(seconds: 15));
    } catch (_) {
      return null;
    }
  }

  // ── SUBMIT ───────────────────────────────────────────────────────────────
  Future<void> _submitProof() async {
    if (_isDelivery) {
      if (_receiverNameController.text.trim().isEmpty ||
          _receiverPhoneController.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter receiver name and phone number.')),
        );
        return;
      }
    }
    if (_proofPhoto == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Please ${_isDelivery ? "take a delivery" : "take a pickup"} photo first.')),
      );
      return;
    }

    setState(() => _isLoading = true);

    // Try to get location — but don't block upload if it fails
    double lat = 0.0;
    double lng = 0.0;
    if (!kIsWeb) {
      try {
        final position = await _getCurrentLocation();
        if (position != null) {
          lat = position.latitude;
          lng = position.longitude;
        }
        // If position is null, we still continue with 0,0
      } catch (_) {
        // Location failed — continue anyway
      }
    }

    try {
      final vehicleId = widget.driverData['driver']?['vehicle_id'];
      final alNumber = widget.order['al_number']?.toString() ?? 
                       widget.order['tracking_number']?.toString() ?? '';
      final tracking = widget.order['tracking_number']?.toString() ?? 
                       widget.order['al_number']?.toString() ?? '';

      // Debug: log what we're sending
      print('🔵 POD order keys: ${widget.order.keys.toList()}');
      print('🔵 POD al_number raw: ${widget.order['al_number']}');
      print('🔵 POD tracking_number raw: ${widget.order['tracking_number']}');
      print('🔵 POD alNumber resolved: $alNumber');
      print('🔵 POD tracking resolved: $tracking');
      print('🔵 POD vehicleId: ${widget.driverData['driver']?['vehicle_id']}');
      print('🔵 POD deliveryId: ${widget.driverData['driver']?['delivery_id']}');
      final deliveryId = widget.driverData['driver']?['delivery_id']?.toString() ?? '';

      if (vehicleId == null) {
        if (mounted) {
          setState(() => _isLoading = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Driver vehicle not found. Please log in again.'),
              backgroundColor: Colors.red,
            ),
          );
        }
        return;
      }

      Map<String, dynamic> result;

      if (_isDelivery) {
        result = await ApiService.uploadDeliveryPOD(
          tracking: tracking,
          alNumber: alNumber,
          vehicleId: vehicleId.toString(),
          deliveryDriverId: deliveryId,
          podPhoto: _proofPhoto!,
          receiverName: _receiverNameController.text.trim(),
          receiverPhoneNumber: _receiverPhoneController.text.trim(),
          scannedBarcode: _scannedBarcode,
          latitude: lat,
          longitude: lng,
        );
      } else {
        result = await ApiService.uploadPickupPOD(
          tracking: tracking,
          alNumber: alNumber,
          vehicleId: vehicleId.toString(),
          pickupDriverId: deliveryId,
          podPhoto: _proofPhoto!,
          latitude: lat,
          longitude: lng,
        );
      }

      if (!mounted) return;
      setState(() => _isLoading = false);

      final success = result['status'] == 'success';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? (success ? 'Uploaded successfully' : 'Upload failed')),
          backgroundColor: success ? Colors.green : Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );

      if (success) Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Upload error: ${e.toString()}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  // ── BUILD ────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final alNumber = widget.order['al_number'] ?? 'N/A';

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FB),
      appBar: AppBar(
        title: Text(_isDelivery ? 'Proof of Delivery' : 'Pickup Confirmation'),
        backgroundColor: AppTheme.primaryBlue,
        foregroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_rounded),
          onPressed: () => Navigator.maybePop(context),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 16),
                  Text('Please wait...', style: TextStyle(color: Colors.grey)),
                ],
              ),
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // ── Order Header ─────────────────────────────────────────
                  _buildHeaderCard(alNumber),
                  const SizedBox(height: 16),

                  // ── Photo Section ────────────────────────────────────────
                  _buildPhotoSection(),
                  const SizedBox(height: 16),

                  // ── Barcode ──────────────────────────────────────────────
                  _buildBarcodeRow(),
                  const SizedBox(height: 16),

                  // ── Receiver Details (delivery only) ─────────────────────
                  if (_isDelivery) ...[
                    _buildReceiverCard(),
                    const SizedBox(height: 16),
                  ],

                  // ── Submit ───────────────────────────────────────────────
                  _buildSubmitButton(),
                  const SizedBox(height: 32),
                ],
              ),
            ),
    );
  }

  Widget _buildHeaderCard(String alNumber) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryBlue.withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              _isDelivery ? Icons.local_shipping_rounded : Icons.inventory_2_rounded,
              color: Colors.white,
              size: 26,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'AL: $alNumber',
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  _isDelivery
                      ? 'Deliver to: ${widget.order['receiver_name'] ?? 'N/A'}'
                      : 'Pickup from: ${widget.order['sender_name'] ?? 'N/A'}',
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPhotoSection() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, 3)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section title
          Row(
            children: [
              Icon(Icons.photo_camera_rounded, color: _accent, size: 20),
              const SizedBox(width: 8),
              Text(
                _isDelivery ? 'Delivery Photo *' : 'Pickup Photo *',
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppTheme.textPrimary),
              ),
            ],
          ),
          const SizedBox(height: 16),

          if (_proofPhoto != null && _photoBytes != null) ...[
            // ── PHOTO PREVIEW ────────────────────────────────────────────
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.memory(
                _photoBytes!,
                height: 200,
                width: double.infinity,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(height: 12),
            // Retake/Change row
            Row(
              children: [
                Expanded(
                  child: _photoActionBtn(
                    icon: Icons.camera_alt_rounded,
                    label: 'Retake',
                    color: AppTheme.primaryBlue,
                    filled: true,
                    onTap: () => _pickImage(ImageSource.camera),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _photoActionBtn(
                    icon: Icons.photo_library_rounded,
                    label: 'Change',
                    color: AppTheme.primaryBlue,
                    filled: false,
                    onTap: () => _pickImage(ImageSource.gallery),
                  ),
                ),
              ],
            ),
          ] else ...[
            // ── CAMERA + GALLERY BUTTONS ──────────────────────────────────
            Row(
              children: [
                // Camera — teal/green gradient
                Expanded(
                  child: GestureDetector(
                    onTap: () => _pickImage(ImageSource.camera),
                    child: Container(
                      height: 120,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF00897B), Color(0xFF00BFA5)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF00897B).withOpacity(0.4),
                            blurRadius: 14,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: Stack(
                        children: [
                          // Background icon watermark
                          Positioned(
                            right: -10,
                            bottom: -10,
                            child: Icon(
                              Icons.camera_alt_rounded,
                              size: 70,
                              color: Colors.white.withOpacity(0.12),
                            ),
                          ),
                          const Padding(
                            padding: EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                CircleAvatar(
                                  radius: 22,
                                  backgroundColor: Colors.white24,
                                  child: Icon(Icons.camera_alt_rounded, color: Colors.white, size: 22),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Camera',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                      ),
                                    ),
                                    Text(
                                      'Take new photo',
                                      style: TextStyle(color: Colors.white70, fontSize: 11),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                // Gallery — purple gradient
                Expanded(
                  child: GestureDetector(
                    onTap: () => _pickImage(ImageSource.gallery),
                    child: Container(
                      height: 120,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF7B1FA2), Color(0xFFAB47BC)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF7B1FA2).withOpacity(0.4),
                            blurRadius: 14,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: Stack(
                        children: [
                          Positioned(
                            right: -10,
                            bottom: -10,
                            child: Icon(
                              Icons.photo_library_rounded,
                              size: 70,
                              color: Colors.white.withOpacity(0.12),
                            ),
                          ),
                          const Padding(
                            padding: EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                CircleAvatar(
                                  radius: 22,
                                  backgroundColor: Colors.white24,
                                  child: Icon(Icons.photo_library_rounded, color: Colors.white, size: 22),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Gallery',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                      ),
                                    ),
                                    Text(
                                      'Pick from gallery',
                                      style: TextStyle(color: Colors.white70, fontSize: 11),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _photoActionBtn({
    required IconData icon,
    required String label,
    required Color color,
    required bool filled,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: filled ? color : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color, width: 1.5),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: filled ? Colors.white : color),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: filled ? Colors.white : color,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBarcodeRow() {
    final scanned = _scannedBarcode != null;
    return GestureDetector(
      onTap: _scanBarcode,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: scanned ? Colors.green.withOpacity(0.5) : Colors.grey.withOpacity(0.25),
          ),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2)),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                color: (scanned ? Colors.green : Colors.orange).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                Icons.qr_code_scanner_rounded,
                color: scanned ? Colors.green : Colors.orange,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    scanned ? 'Barcode Scanned ✓' : 'Scan Package Barcode',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: scanned ? Colors.green : AppTheme.textPrimary,
                    ),
                  ),
                  Text(
                    scanned ? _scannedBarcode! : 'Optional — tap to scan',
                    style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: Colors.grey[400]),
          ],
        ),
      ),
    );
  }

  Widget _buildReceiverCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, 3)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.person_rounded, color: _accent, size: 20),
              const SizedBox(width: 8),
              const Text('Receiver Details *',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppTheme.textPrimary)),
            ],
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _receiverNameController,
            decoration: InputDecoration(
              labelText: 'Receiver Name',
              hintText: "Full name",
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              prefixIcon: const Icon(Icons.person_outline_rounded),
              filled: true,
              fillColor: Colors.grey[50],
              contentPadding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _receiverPhoneController,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(
              labelText: 'Phone Number',
              hintText: "Contact number",
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              prefixIcon: const Icon(Icons.phone_outlined),
              filled: true,
              fillColor: Colors.grey[50],
              contentPadding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      height: 54,
      child: ElevatedButton.icon(
        onPressed: _isLoading ? null : _submitProof,
        icon: const Icon(Icons.check_circle_rounded, size: 22),
        label: Text(
          _isDelivery ? 'Submit Proof of Delivery' : 'Submit Pickup Confirmation',
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        ),
        style: ElevatedButton.styleFrom(
          backgroundColor: _accent,
          foregroundColor: Colors.white,
          disabledBackgroundColor: Colors.grey[300],
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          elevation: 4,
          shadowColor: _accent.withOpacity(0.4),
        ),
      ),
    );
  }
}
