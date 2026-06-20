import 'package:flutter/material.dart';
import 'dart:io';
import '../../services/api_service.dart';
import '../../services/permission_service.dart';
import '../../config/app_theme.dart';
import 'package:image_picker/image_picker.dart';

class DriverKYCScreen extends StatefulWidget {
  final String firebaseUid;
  final String driverName;
  final String driverMobile;
  final String vehicleNumber;

  const DriverKYCScreen({
    super.key,
    required this.firebaseUid,
    required this.driverName,
    required this.driverMobile,
    this.vehicleNumber = '',
  });

  @override
  State<DriverKYCScreen> createState() => _DriverKYCScreenState();
}

class _DriverKYCScreenState extends State<DriverKYCScreen> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _picker = ImagePicker();
  
  bool _isLoading = false;
  bool _isSubmitting = false;

  // Terms & Conditions
  bool _agreedToTerms = false;
  
  // Form controllers
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _mobileController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _aadharController = TextEditingController();
  final TextEditingController _panController = TextEditingController();
  final TextEditingController _licenseController = TextEditingController();
  final TextEditingController _vehicleNumberController = TextEditingController();
  
  // Document images
  XFile? _aadharFrontImage;
  XFile? _aadharBackImage;
  XFile? _panImage;
  XFile? _licenseFrontImage;
  XFile? _licenseBackImage;
  
  // Vehicle Document images
  XFile? _rcFrontImage;
  XFile? _rcBackImage;
  XFile? _insuranceImage;
  XFile? _fitnessImage;
  XFile? _pucImage;
  XFile? _vehicleFrontImage;
  XFile? _vehicleSideImage;
  
  // Flags for existing images in database
  bool _hasAadharFront = false;
  bool _hasAadharBack = false;
  bool _hasPan = false;
  bool _hasLicenseFront = false;
  bool _hasLicenseBack = false;
  bool _hasRcFront = false;
  bool _hasRcBack = false;
  bool _hasInsurance = false;
  bool _hasFitness = false;
  bool _hasPuc = false;
  bool _hasVehicleFront = false;
  bool _hasVehicleSide = false;
  String? _kycStatus;

  @override
  void initState() {
    super.initState();
    _nameController.text = widget.driverName;
    _mobileController.text = widget.driverMobile;
    _vehicleNumberController.text = widget.vehicleNumber; // Automatically set from login data!

    _loadKYCStatus();
  }


  @override
  void dispose() {
    _nameController.dispose();
    _mobileController.dispose();
    _emailController.dispose();
    _aadharController.dispose();
    _panController.dispose();
    _licenseController.dispose();
    _vehicleNumberController.dispose();
    super.dispose();
  }

  Future<void> _loadKYCStatus() async {
    setState(() => _isLoading = true);
    
    try {
      final result = await ApiService.getDriverKYCStatus(firebaseUid: widget.firebaseUid);
      
      if (result['status'] == 'success' && result['kyc_exists'] == true) {
        final kycData = result['kyc_data'];
        setState(() {
          _kycStatus = kycData['kyc_status'];
          
          if (kycData['driver_name'] != null && kycData['driver_name'].toString().isNotEmpty) {
            _nameController.text = kycData['driver_name'];
          }
          if (kycData['driver_mobile'] != null && kycData['driver_mobile'].toString().isNotEmpty) {
            _mobileController.text = kycData['driver_mobile'];
          }
          
          _emailController.text = kycData['driver_email'] ?? '';
          _aadharController.text = kycData['aadhar_number']?.toString() ?? '';
          _panController.text = kycData['pan_number']?.toString() ?? '';
          _licenseController.text = kycData['license_number']?.toString() ?? '';
          _vehicleNumberController.text = kycData['vehicle_number']?.toString() ?? '';
          
          _hasAadharFront = kycData['aadhar_front_image'] != null && kycData['aadhar_front_image'].toString().isNotEmpty;
          _hasAadharBack = kycData['aadhar_back_image'] != null && kycData['aadhar_back_image'].toString().isNotEmpty;
          _hasPan = kycData['pan_image'] != null && kycData['pan_image'].toString().isNotEmpty;
          _hasLicenseFront = kycData['license_front_image'] != null && kycData['license_front_image'].toString().isNotEmpty;
          _hasLicenseBack = kycData['license_back_image'] != null && kycData['license_back_image'].toString().isNotEmpty;
          _hasRcFront = kycData['rc_front_image'] != null && kycData['rc_front_image'].toString().isNotEmpty;
          _hasRcBack = kycData['rc_back_image'] != null && kycData['rc_back_image'].toString().isNotEmpty;
          _hasInsurance = kycData['insurance_image'] != null && kycData['insurance_image'].toString().isNotEmpty;
          _hasFitness = kycData['fitness_image'] != null && kycData['fitness_image'].toString().isNotEmpty;
          _hasPuc = kycData['puc_image'] != null && kycData['puc_image'].toString().isNotEmpty;
          _hasVehicleFront = kycData['vehicle_photo_front'] != null && kycData['vehicle_photo_front'].toString().isNotEmpty;
          _hasVehicleSide = kycData['vehicle_photo_side'] != null && kycData['vehicle_photo_side'].toString().isNotEmpty;
        });
      }
    } catch (e) {
      print('Error loading KYC status: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _pickImage(String documentType, ImageSource source) async {
    // Request appropriate permission before picking
    bool granted;
    if (source == ImageSource.camera) {
      granted = await PermissionService.requestCamera(context);
    } else {
      granted = await PermissionService.requestStorage(context);
    }
    if (!granted) return;

    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        imageQuality: 70,
      );
      
      if (image != null) {
        setState(() {
          switch (documentType) {
            case 'aadhar_front':
              _aadharFrontImage = image;
              break;
            case 'aadhar_back':
              _aadharBackImage = image;
              break;
            case 'pan':
              _panImage = image;
              break;
            case 'license_front':
              _licenseFrontImage = image;
              break;
            case 'license_back':
              _licenseBackImage = image;
              break;
            case 'rc_front':
              _rcFrontImage = image;
              break;
            case 'rc_back':
              _rcBackImage = image;
              break;
            case 'insurance':
              _insuranceImage = image;
              break;
            case 'fitness':
              _fitnessImage = image;
              break;
            case 'puc':
              _pucImage = image;
              break;
            case 'vehicle_front':
              _vehicleFrontImage = image;
              break;
            case 'vehicle_side':
              _vehicleSideImage = image;
              break;
          }
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error picking image: $e'), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _submitKYC() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    // Check if all documents are uploaded (either selected now or already in DB)
    bool missingAadharFront = _aadharFrontImage == null && !_hasAadharFront;
    bool missingAadharBack = _aadharBackImage == null && !_hasAadharBack;
    bool missingPan = _panImage == null && !_hasPan;
    bool missingLicenseFront = _licenseFrontImage == null && !_hasLicenseFront;
    bool missingLicenseBack = _licenseBackImage == null && !_hasLicenseBack;
    
    // Vehicle Document missing checks
    bool missingRcFront = _rcFrontImage == null && !_hasRcFront;
    bool missingRcBack = _rcBackImage == null && !_hasRcBack;
    bool missingInsurance = _insuranceImage == null && !_hasInsurance;
    bool missingFitness = _fitnessImage == null && !_hasFitness;
    bool missingPuc = _pucImage == null && !_hasPuc;
    bool missingVehicleFront = _vehicleFrontImage == null && !_hasVehicleFront;
    bool missingVehicleSide = _vehicleSideImage == null && !_hasVehicleSide;

    if (missingAadharFront || missingAadharBack || missingPan || missingLicenseFront || missingLicenseBack ||
        missingRcFront || missingRcBack || missingInsurance || missingFitness || missingPuc || 
        missingVehicleFront || missingVehicleSide) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please upload all required documents'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      // Submit KYC data
      final result = await ApiService.submitDriverKYC(
        firebaseUid: widget.firebaseUid,
        driverName: _nameController.text,
        driverMobile: _mobileController.text,
        driverEmail: _emailController.text,
        aadharNumber: _aadharController.text,
        panNumber: _panController.text,
        licenseNumber: _licenseController.text,
        vehicleNumber: _vehicleNumberController.text,
        address: '',
        city: '',
        state: '',
        pincode: '',
      );

      if (result['status'] == 'success') {
        // Upload documents only if new ones were selected
        if (_aadharFrontImage != null) await _uploadDocument('aadhar_front', _aadharFrontImage!);
        if (_aadharBackImage != null) await _uploadDocument('aadhar_back', _aadharBackImage!);
        if (_panImage != null) await _uploadDocument('pan', _panImage!);
        if (_licenseFrontImage != null) await _uploadDocument('license_front', _licenseFrontImage!);
        if (_licenseBackImage != null) await _uploadDocument('license_back', _licenseBackImage!);
        if (_rcFrontImage != null) await _uploadDocument('rc_front', _rcFrontImage!);
        if (_rcBackImage != null) await _uploadDocument('rc_back', _rcBackImage!);
        if (_insuranceImage != null) await _uploadDocument('insurance', _insuranceImage!);
        if (_fitnessImage != null) await _uploadDocument('fitness', _fitnessImage!);
        if (_pucImage != null) await _uploadDocument('puc', _pucImage!);
        if (_vehicleFrontImage != null) await _uploadDocument('vehicle_front', _vehicleFrontImage!);
        if (_vehicleSideImage != null) await _uploadDocument('vehicle_side', _vehicleSideImage!);

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('KYC submitted successfully!'),
            backgroundColor: AppTheme.accentGreen,
          ),
        );

        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Failed to submit KYC'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  Future<void> _uploadDocument(String documentType, XFile imageFile) async {
    await ApiService.uploadDriverKYCDocument(
      firebaseUid: widget.firebaseUid,
      documentType: documentType,
      imageFile: imageFile,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Driver KYC')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      appBar: AppBar(
        title: const Text('Driver KYC Verification'),
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        flexibleSpace: Container(
          decoration: BoxDecoration(gradient: AppTheme.primaryGradient),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
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
                child: Column(
                  children: [
                    Container(
                      width: 70,
                      height: 70,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.verified_user_rounded,
                        size: 36,
                        color: Color(0xFF0D2E6E),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'KYC Verification',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Complete your verification to start receiving orders',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.white.withOpacity(0.9),
                      ),
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 20),
              if (_kycStatus != null && _kycStatus!.isNotEmpty) ...[
                _buildStatusCard(),
                const SizedBox(height: 20),
              ],
              
              // Section 1: Details
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppTheme.cardShadow,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.person_outline, color: AppTheme.primaryBlue),
                        const SizedBox(width: 8),
                        Text('Personal Details', style: AppTheme.heading2),
                      ],
                    ),
                    const Divider(height: 24),
                    _buildTextField('Full Name', _nameController, Icons.person),
                    _buildTextField('Mobile Number', _mobileController, Icons.phone),
                    _buildTextField('Email (Optional)', _emailController, Icons.email, required: false),
                  ],
                ),
              ),
              
              const SizedBox(height: 24),
              
              // Section 2: Photos/Documents
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppTheme.cardShadow,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.photo_camera_back_outlined, color: AppTheme.primaryBlue),
                        const SizedBox(width: 8),
                        Text('Upload Photos', style: AppTheme.heading2),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Please upload clear photos of your original documents.',
                      style: AppTheme.bodyMedium.copyWith(color: Colors.grey[600]),
                    ),
                    const Divider(height: 24),
                    
                    _buildTextField('Aadhar Number', _aadharController, Icons.credit_card),
                    _buildDocumentUpload('Aadhar Front', 'aadhar_front', _aadharFrontImage, hasExisting: _hasAadharFront),
                    _buildDocumentUpload('Aadhar Back', 'aadhar_back', _aadharBackImage, hasExisting: _hasAadharBack),
                    
                    const SizedBox(height: 24),
                    _buildTextField('PAN Number', _panController, Icons.credit_card),
                    _buildDocumentUpload('PAN Card Photo', 'pan', _panImage, hasExisting: _hasPan),
                    
                    const SizedBox(height: 24),
                    _buildTextField('License Number', _licenseController, Icons.credit_card),
                    _buildDocumentUpload('License Front', 'license_front', _licenseFrontImage, hasExisting: _hasLicenseFront),
                    _buildDocumentUpload('License Back', 'license_back', _licenseBackImage, hasExisting: _hasLicenseBack),
                  ],
                ),
              ),
              
              const SizedBox(height: 24),
              
              // Section 3: Vehicle Documents
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppTheme.cardShadow,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.local_shipping_outlined, color: AppTheme.primaryBlue),
                        const SizedBox(width: 8),
                        Text('Vehicle Documents', style: AppTheme.heading2),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Please enter your vehicle number and upload clear photos of your vehicle documents.',
                      style: TextStyle(fontSize: 14, color: Colors.grey[600]),
                    ),
                    const Divider(height: 24),
                    
                    _buildTextField('Vehicle Number', _vehicleNumberController, Icons.directions_car),
                    const SizedBox(height: 16),

                    _buildDocumentUpload('RC Front', 'rc_front', _rcFrontImage, hasExisting: _hasRcFront),
                    _buildDocumentUpload('RC Back', 'rc_back', _rcBackImage, hasExisting: _hasRcBack),
                    
                    const SizedBox(height: 16),
                    _buildDocumentUpload('Insurance Document', 'insurance', _insuranceImage, hasExisting: _hasInsurance),
                    
                    const SizedBox(height: 16),
                    _buildDocumentUpload('Fitness Certificate', 'fitness', _fitnessImage, hasExisting: _hasFitness),
                    
                    const SizedBox(height: 16),
                    _buildDocumentUpload('PUC Certificate', 'puc', _pucImage, hasExisting: _hasPuc),
                    
                    const SizedBox(height: 16),
                    _buildDocumentUpload('Vehicle Photo (Front)', 'vehicle_front', _vehicleFrontImage, hasExisting: _hasVehicleFront),
                    _buildDocumentUpload('Vehicle Photo (Side)', 'vehicle_side', _vehicleSideImage, hasExisting: _hasVehicleSide),
                  ],
                ),
              ),
              
              const SizedBox(height: 32),

              // ── Terms & Conditions ──────────────────────────────────────
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 24,
                    width: 24,
                    child: Checkbox(
                      value: _agreedToTerms,
                      activeColor: const Color(0xFF10B981),
                      onChanged: (value) {
                        setState(() => _agreedToTerms = value ?? false);
                      },
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: GestureDetector(
                      onTap: () => _showDriverTermsDialog(),
                      child: RichText(
                        text: const TextSpan(
                          text: 'I agree to the ',
                          style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                          children: [
                            TextSpan(
                              text: 'Terms and Conditions',
                              style: TextStyle(
                                color: Color(0xFF3B82F6),
                                fontWeight: FontWeight.w600,
                                decoration: TextDecoration.underline,
                              ),
                            ),
                            TextSpan(text: ' of Truck Union.'),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: (_isSubmitting || !_agreedToTerms) ? null : _submitKYC,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF0D2E6E),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isSubmitting
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Submit KYC', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusCard() {
    // Return empty widget if no status
    if (_kycStatus == null || _kycStatus!.isEmpty) {
      return const SizedBox.shrink();
    }
    
    // Use local variable to avoid null issues
    final String status = _kycStatus ?? 'pending';
    
    Color statusColor;
    String statusText;
    IconData statusIcon;

    switch (status.toLowerCase()) {
      case 'verified':
        statusColor = AppTheme.accentGreen;
        statusText = 'Verified';
        statusIcon = Icons.check_circle;
        break;
      case 'rejected':
        statusColor = Colors.red;
        statusText = 'Rejected';
        statusIcon = Icons.cancel;
        break;
      case 'submitted':
      case 'under_review':
        statusColor = AppTheme.accentOrange;
        statusText = 'Under Review';
        statusIcon = Icons.pending;
        break;
      default:
        statusColor = Colors.grey;
        statusText = 'Pending';
        statusIcon = Icons.info;
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: statusColor.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: statusColor),
      ),
      child: Row(
        children: [
          Icon(statusIcon, color: statusColor, size: 32),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('KYC Status', style: AppTheme.caption),
                Text(statusText, style: TextStyle(color: statusColor, fontSize: 18, fontWeight: FontWeight.bold)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showDriverTermsDialog() {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFF0D2E6E), Color(0xFF1E5BA8)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.gavel, color: Colors.white, size: 24),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text('Driver Terms & Conditions',
                        style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white)),
                  ),
                ],
              ),
            ),
            SizedBox(
              height: 400,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Text(
                  '''1. Acceptance of Terms\nBy using this application, I agree to comply with all company policies, rules, and regulations.\n\n2. Valid Documents\nI confirm that I possess a valid driving license and all required documents and will keep them updated at all times.\n\n3. Accurate Information\nI agree to provide accurate and complete personal, vehicle, and employment information.\n\n4. Compliance with Traffic Laws\nI will follow all traffic rules, road safety regulations, and government laws while performing my duties.\n\n5. Safe Driving\nI will operate the vehicle safely and responsibly and avoid rash or negligent driving at all times.\n\n6. Attendance & Punctuality\nI will report for duty on time, complete assigned trips, and inform the company in advance about any absence.\n\n7. Vehicle Care\nI will maintain the assigned vehicle in good condition, report any damage immediately, and not use it for personal purposes without permission.\n\n8. No Alcohol or Drugs\nI will not consume alcohol, drugs, or any intoxicating substances before or during duty hours.\n\n9. Professional Conduct\nI will behave professionally and respectfully with customers, colleagues, and the public at all times.\n\n10. Order Compliance\nI will accept and complete assigned orders diligently and update order status accurately in the app.\n\n11. Confidentiality\nI will not share customer information, delivery details, or company data with unauthorized persons.\n\n12. Reporting Incidents\nI will immediately report any accidents, theft, or incidents to the company and cooperate fully in investigations.\n\n13. Suspension or Termination\nThe company may suspend or terminate my access for policy violations, misconduct, or non-compliance with these terms.\n\n14. Modification of Terms\nThe company reserves the right to amend these Terms & Conditions at any time. Continued use constitutes acceptance.''',
                  style: const TextStyle(fontSize: 13, color: Color(0xFF1E293B), height: 1.6),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(ctx),
                      style: OutlinedButton.styleFrom(
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text('Close'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        setState(() => _agreedToTerms = true);
                        Navigator.pop(ctx);
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.accentGreen,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text('I Agree', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTextField(String label, TextEditingController controller, IconData icon, {bool required = true, int maxLines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        maxLines: maxLines,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
        validator: required ? (value) {
          if (value == null || value.isEmpty) {
            return 'Please enter $label';
          }
          return null;
        } : null,
      ),
    );
  }

  Widget _buildDocumentUpload(String label, String documentType, XFile? imageFile, {bool hasExisting = false}) {
    bool isUploaded = imageFile != null || hasExisting;
    return GestureDetector(
      onTap: () => _showImageSourceDialog(documentType),
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.03),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            border: Border.all(
              color: isUploaded ? AppTheme.accentGreen : Colors.grey.shade300,
              width: 1.5,
            ),
            borderRadius: BorderRadius.circular(12),
            color: isUploaded ? AppTheme.accentGreen.withOpacity(0.05) : Colors.grey.shade50,
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: isUploaded ? AppTheme.accentGreen.withOpacity(0.2) : Colors.white,
                  shape: BoxShape.circle,
                  boxShadow: [
                    if (!isUploaded)
                      BoxShadow(
                        color: Colors.black.withOpacity(0.05),
                        blurRadius: 4,
                        offset: const Offset(0, 2),
                      ),
                  ],
                ),
                child: Icon(
                  isUploaded ? Icons.check_circle : Icons.camera_alt_outlined,
                  color: isUploaded ? AppTheme.accentGreen : AppTheme.primaryBlue,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: isUploaded ? AppTheme.accentGreen : AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      isUploaded 
                          ? (imageFile != null ? 'New photo selected' : 'Document already uploaded') 
                          : 'Tap to capture or upload photo',
                      style: TextStyle(
                        fontSize: 12,
                        color: isUploaded ? AppTheme.accentGreen.withOpacity(0.8) : Colors.grey[600],
                      ),
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

  void _showImageSourceDialog(String documentType) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 24),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const Text(
              'Upload Photo',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Choose how you want to upload the photo',
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _buildSourceOption(
                  icon: Icons.camera_alt,
                  label: 'Camera',
                  color: AppTheme.primaryBlue,
                  onTap: () {
                    Navigator.pop(context);
                    _pickImage(documentType, ImageSource.camera);
                  },
                ),
                _buildSourceOption(
                  icon: Icons.photo_library,
                  label: 'Gallery',
                  color: AppTheme.accentPurple,
                  onTap: () {
                    Navigator.pop(context);
                    _pickImage(documentType, ImageSource.gallery);
                  },
                ),
              ],
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildSourceOption({
    required IconData icon,
    required String label,
    required Color color,
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
              color: color.withOpacity(0.1),
              shape: BoxShape.circle,
              border: Border.all(color: color.withOpacity(0.2)),
            ),
            child: Icon(icon, color: color, size: 32),
          ),
          const SizedBox(height: 12),
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppTheme.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
