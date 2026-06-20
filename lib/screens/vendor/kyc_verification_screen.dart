import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../services/permission_service.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';

class KYCVerificationScreen extends StatefulWidget {
  const KYCVerificationScreen({super.key});

  @override
  State<KYCVerificationScreen> createState() => _KYCVerificationScreenState();
}

class _KYCVerificationScreenState extends State<KYCVerificationScreen> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  
  final _formKey = GlobalKey<FormState>();
  
  // Form controllers
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _aadhaarCtrl = TextEditingController();
  final _panCtrl = TextEditingController();
  final _gstCtrl = TextEditingController();
  final _companyNameCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _bankAccountNameCtrl = TextEditingController();
  final _bankAccountNumberCtrl = TextEditingController();
  final _ifscCodeCtrl = TextEditingController();
  
  // Document files
  PlatformFile? _aadhaarFile;
  PlatformFile? _panFile;
  PlatformFile? _gstFile;
  PlatformFile? _addressProofFile;
  PlatformFile? _photoFile;
  PlatformFile? _bankAccountPhotoFile;
  
  // Uploaded document names (from server)
  String? _uploadedAadhaar;
  String? _uploadedPan;
  String? _uploadedGst;
  String? _uploadedAddress;
  String? _uploadedPhoto;
  String? _uploadedBankAccountPhoto;
  
  bool _isSubmitting = false;
  bool _isLoading = true;
  String _verificationStatus = 'pending';

  // Expansion states for collapsible sections
  bool _personalDetailsExpanded = true;
  bool _businessDetailsExpanded = false;
  bool _bankDetailsExpanded = false;
  bool _documentsExpanded = false;

  // Account type selection
  String _accountType = 'individual'; // 'individual' or 'business'

  // Terms & Conditions
  bool _agreedToTerms = false;

  @override
  void initState() {
    super.initState();
    _loadKYCStatus();
  }

  Future<void> _loadKYCStatus() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;

    try {
      final response = await ApiService.getKYCStatus(firebaseUid: user.uid);
      
      if (response['status'] == 'success' && response['kyc_status'] != null) {
        setState(() {
          _verificationStatus = response['kyc_status'];
          
          if (response['name'] != null) _nameCtrl.text = response['name'];
          if (response['email'] != null) _emailCtrl.text = response['email'];
          if (response['phone'] != null) _phoneCtrl.text = response['phone'];
          if (response['aadhaar_number'] != null) _aadhaarCtrl.text = response['aadhaar_number'];
          if (response['pan_number'] != null) _panCtrl.text = response['pan_number'];
          if (response['gst_number'] != null) _gstCtrl.text = response['gst_number'];
          if (response['company_name'] != null) _companyNameCtrl.text = response['company_name'];
          if (response['address'] != null) _addressCtrl.text = response['address'];
          if (response['account_type'] != null) _accountType = response['account_type'];
          if (response['bank_account_name'] != null) _bankAccountNameCtrl.text = response['bank_account_name'];
          if (response['bank_account_number'] != null) _bankAccountNumberCtrl.text = response['bank_account_number'];
          if (response['ifsc_code'] != null) _ifscCodeCtrl.text = response['ifsc_code'];
          
          if (response['documents'] != null) {
            final docs = response['documents'] as Map<String, dynamic>;
            _uploadedAadhaar = docs['aadhaar'];
            _uploadedPan = docs['pan'];
            _uploadedPhoto = docs['photo'];
            _uploadedGst = docs['gst'];
            _uploadedAddress = docs['address_proof'];
            _uploadedBankAccountPhoto = docs['bank_account_photo'];
          }
          
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      setState(() => _isLoading = false);
      print('Error loading KYC status: $e');
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _aadhaarCtrl.dispose();
    _panCtrl.dispose();
    _gstCtrl.dispose();
    _companyNameCtrl.dispose();
    _addressCtrl.dispose();
    _bankAccountNameCtrl.dispose();
    _bankAccountNumberCtrl.dispose();
    _ifscCodeCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickFile(String docType) async {
    final source = await showModalBottomSheet<String>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Container(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Upload Document',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Choose how you want to upload',
              style: TextStyle(fontSize: 14, color: Color(0xFF64748B)),
            ),
            const SizedBox(height: 24),
            ListTile(
              leading: Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFF3B82F6).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.camera_alt, color: Color(0xFF3B82F6), size: 24),
              ),
              title: const Text('Take Photo', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              subtitle: const Text('Use camera to capture document', style: TextStyle(fontSize: 13)),
              onTap: () => Navigator.pop(context, 'camera'),
            ),
            const SizedBox(height: 12),
            ListTile(
              leading: Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFF10B981).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.photo_library, color: Color(0xFF10B981), size: 24),
              ),
              title: const Text('Choose from Files', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              subtitle: const Text('Select from gallery or file manager', style: TextStyle(fontSize: 13)),
              onTap: () => Navigator.pop(context, 'files'),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel', style: TextStyle(color: Color(0xFF64748B), fontSize: 15, fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ),
    );
    
    if (source == null) return;
    
    try {
      if (source == 'camera') {
        await _pickFromCamera(docType);
      } else {
        await _pickFromFiles(docType);
      }
    } catch (e) {
      _showSnackBar('Error: $e');
    }
  }

  Future<void> _pickFromCamera(String docType) async {
    // Request camera permission first
    final granted = await PermissionService.requestCamera(context);
    if (!granted) return;
    try {
      final ImagePicker picker = ImagePicker();
      final XFile? image = await picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
        maxWidth: 1920,
        maxHeight: 1920,
        preferredCameraDevice: CameraDevice.rear,
        requestFullMetadata: false,
      );
      
      if (!mounted) return;
      
      if (image != null) {
        final bytes = await image.readAsBytes();
        if (!mounted) return;
        
        final platformFile = PlatformFile(
          name: image.name,
          size: bytes.length,
          path: image.path,
          bytes: bytes,
        );
        
        setState(() {
          switch (docType) {
            case 'aadhaar': _aadhaarFile = platformFile; break;
            case 'pan': _panFile = platformFile; break;
            case 'gst': _gstFile = platformFile; break;
            case 'address': _addressProofFile = platformFile; break;
            case 'photo': _photoFile = platformFile; break;
            case 'bank_account': _bankAccountPhotoFile = platformFile; break;
          }
        });
        
        if (mounted) _showSnackBar('Photo captured successfully', isError: false);
      }
    } catch (e) {
      print('Error capturing photo: $e');
      if (mounted) _showSnackBar('Error capturing photo. Please try again.');
    }
  }
  
  Future<void> _pickFromFiles(String docType) async {
    if (!kIsWeb && Platform.isAndroid) {
      final granted = await PermissionService.requestStorage(context);
      if (!granted) return;
    }
    
    try {
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
      );

      if (!mounted) return;

      if (result != null) {
        setState(() {
          switch (docType) {
            case 'aadhaar': _aadhaarFile = result.files.first; break;
            case 'pan': _panFile = result.files.first; break;
            case 'gst': _gstFile = result.files.first; break;
            case 'address': _addressProofFile = result.files.first; break;
            case 'photo': _photoFile = result.files.first; break;
            case 'bank_account': _bankAccountPhotoFile = result.files.first; break;
          }
        });
        
        if (mounted) _showSnackBar('File selected successfully', isError: false);
      }
    } catch (e) {
      print('Error picking file: $e');
      if (mounted) _showSnackBar('Error picking file. Please try again.');
    }
  }
  

  Future<void> _submitKYC() async {
    if (!_formKey.currentState!.validate()) {
      _showSnackBar('Please fill all required fields correctly');
      return;
    }
    
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) {
      _showSnackBar('User not logged in');
      return;
    }
    
    setState(() => _isSubmitting = true);
    
    // Check if KYC details already exist in database (prevent duplicates)
    try {
      final checkResponse = await ApiService.checkKYCExists(
        firebaseUid: user.uid,
        aadhaarNumber: _aadhaarCtrl.text,
        panNumber: _panCtrl.text,
        bankAccountNumber: _bankAccountNumberCtrl.text,
      );
      
      if (checkResponse['status'] == 'exists') {
        setState(() => _isSubmitting = false);
        
        List<String> existingFields = [];
        if (checkResponse['aadhaar_exists'] == true) existingFields.add('Aadhaar number');
        if (checkResponse['pan_exists'] == true) existingFields.add('PAN number');
        if (checkResponse['bank_account_exists'] == true) existingFields.add('Bank account number');
        
        _showSnackBar(
          '${existingFields.join(", ")} already registered with another account. Please use different details.',
        );
        return;
      }
    } catch (e) {
      setState(() => _isSubmitting = false);
      _showSnackBar('Error checking KYC details: $e');
      return;
    }
    
    List<String> missingDocs = [];
    if (_aadhaarFile == null && _uploadedAadhaar == null) missingDocs.add('Aadhaar Card');
    if (_panFile == null && _uploadedPan == null) missingDocs.add('PAN Card');
    if (_photoFile == null && _uploadedPhoto == null) missingDocs.add('Passport Photo');
    if (_bankAccountPhotoFile == null && _uploadedBankAccountPhoto == null) missingDocs.add('Bank Account Photo');
    
    // Additional validation for business accounts
    if (_accountType == 'business') {
      if (_gstFile == null && _uploadedGst == null) missingDocs.add('GST Certificate');
      if (_addressProofFile == null && _uploadedAddress == null) missingDocs.add('Address Proof');
    }
    
    if (missingDocs.isNotEmpty) {
      setState(() => _isSubmitting = false);
      _showSnackBar('Please upload: ${missingDocs.join(', ')}');
      return;
    }
    
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(Icons.info_outline, color: Color(0xFF3B82F6), size: 28),
            SizedBox(width: 12),
            Text('Confirm Submission', style: TextStyle(fontWeight: FontWeight.w800)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('You are about to submit your ${_accountType == 'business' ? 'business' : 'individual'} KYC:'),
            SizedBox(height: 12),
            if (_accountType == 'business') ...[
              _buildConfirmItem('✓ Company: ${_companyNameCtrl.text}'),
              _buildConfirmItem('✓ GST: ${_gstCtrl.text}'),
            ],
            _buildConfirmItem('✓ Aadhaar: ${_aadhaarCtrl.text}'),
            _buildConfirmItem('✓ PAN: ${_panCtrl.text}'),
            SizedBox(height: 12),
            Text('Make sure all information is correct. Verification takes 24-48 hours.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Color(0xFF10B981),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: Text('Submit', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
    
    if (confirm != true) return;
    
    setState(() => _isSubmitting = true);
    
    try {
      Uri uploadUri = Uri.https('crm.abra-logistic.com', '/api1/vendor/upload_kyc_documents.php');
      var request = http.MultipartRequest('POST', uploadUri);
      
      request.fields['firebase_uid'] = user.uid;
      request.fields['account_type'] = _accountType;
      request.fields['name'] = _nameCtrl.text;
      request.fields['email'] = _emailCtrl.text;
      request.fields['phone'] = _phoneCtrl.text;
      request.fields['aadhaar_number'] = _aadhaarCtrl.text;
      request.fields['pan_number'] = _panCtrl.text;
      if (_gstCtrl.text.isNotEmpty) request.fields['gst_number'] = _gstCtrl.text;
      if (_companyNameCtrl.text.isNotEmpty) request.fields['company_name'] = _companyNameCtrl.text;
      if (_addressCtrl.text.isNotEmpty) request.fields['address'] = _addressCtrl.text;
      request.fields['bank_account_name'] = _bankAccountNameCtrl.text;
      request.fields['bank_account_number'] = _bankAccountNumberCtrl.text;
      request.fields['ifsc_code'] = _ifscCodeCtrl.text;
      
      if (_aadhaarFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('aadhaar', _aadhaarFile!.bytes!, filename: _aadhaarFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('aadhaar', _aadhaarFile!.path!, filename: _aadhaarFile!.name));
        }
      }
      
      if (_panFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('pan', _panFile!.bytes!, filename: _panFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('pan', _panFile!.path!, filename: _panFile!.name));
        }
      }
      
      if (_photoFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('photo', _photoFile!.bytes!, filename: _photoFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('photo', _photoFile!.path!, filename: _photoFile!.name));
        }
      }
      
      if (_gstFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('gst', _gstFile!.bytes!, filename: _gstFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('gst', _gstFile!.path!, filename: _gstFile!.name));
        }
      }
      
      if (_addressProofFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('address_proof', _addressProofFile!.bytes!, filename: _addressProofFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('address_proof', _addressProofFile!.path!, filename: _addressProofFile!.name));
        }
      }
      
      if (_bankAccountPhotoFile != null) {
        if (kIsWeb) {
          request.files.add(http.MultipartFile.fromBytes('bank_account_photo', _bankAccountPhotoFile!.bytes!, filename: _bankAccountPhotoFile!.name));
        } else {
          request.files.add(await http.MultipartFile.fromPath('bank_account_photo', _bankAccountPhotoFile!.path!, filename: _bankAccountPhotoFile!.name));
        }
      }
      
      final streamedResponse = await request.send().timeout(Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      
      setState(() => _isSubmitting = false);
      
      if (response.statusCode == 200 && response.body.isNotEmpty) {
        final responseData = json.decode(response.body);
        if (responseData['status'] == 'success') {
          // Update user type in SharedPreferences based on KYC selection
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('user_type', _accountType);
          
          // Reload KYC status from server to get latest status
          await _loadKYCStatus();
          
          if (mounted) _showSuccessDialog();
        } else {
          _showSnackBar(responseData['message'] ?? 'Failed to submit KYC');
        }
      } else {
        _showSnackBar('Server error: ${response.statusCode}');
      }
    } catch (e) {
      setState(() => _isSubmitting = false);
      _showSnackBar('Error submitting KYC: $e');
    }
  }

  Widget _buildConfirmItem(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(text, style: TextStyle(fontSize: 13, color: Color(0xFF1E293B))),
    );
  }

  void _showSuccessDialog() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('kyc_status_${user.uid}');
      await prefs.remove('kyc_last_checked_${user.uid}');
    }
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Color(0xFF10B981), size: 32),
            SizedBox(width: 12),
            Text('KYC Submitted!', style: TextStyle(fontWeight: FontWeight.w800)),
          ],
        ),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Your KYC documents have been submitted successfully.'),
            SizedBox(height: 12),
            Text('Verification usually takes 24-48 hours. You\'ll receive a notification once verified.', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
          ],
        ),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              if (mounted) context.go('/vendor');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF10B981),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text('Done', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    
    if (_isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFF),
        appBar: AppBar(
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: Color(0xFF0D2E6E)),
            onPressed: () {
              if (context.canPop()) {
                context.pop();
              } else {
                context.go('/vendor');
              }
            },
          ),
          title: const Text('KYC Verification', style: TextStyle(color: Color(0xFF0D2E6E), fontWeight: FontWeight.w800)),
          backgroundColor: Colors.white,
          elevation: 0,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFF),
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF0D2E6E)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/vendor');
            }
          },
        ),
        title: const Text('KYC Verification', style: TextStyle(color: Color(0xFF0D2E6E), fontWeight: FontWeight.w800)),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_verificationStatus == 'submitted') ...[
                _buildStatusBanner(
                  '⏳ Verification in Progress',
                  'Your documents are under review. This usually takes 24-48 hours.',
                  const Color(0xFFFEF3C7),
                  const Color(0xFFF59E0B),
                ),
                const SizedBox(height: 20),
              ],
              
              if (_verificationStatus == 'verified') ...[
                _buildStatusBanner(
                  '✅ Verified',
                  'Your KYC verification is complete. You can now add vehicles.',
                  const Color(0xFFD1FAE5),
                  const Color(0xFF10B981),
                ),
                const SizedBox(height: 20),
              ],
              
              if (_verificationStatus == 'rejected') ...[
                _buildStatusBanner(
                  '❌ Verification Failed',
                  'Please re-upload correct documents or contact support.',
                  const Color(0xFFFEE2E2),
                  const Color(0xFFEF4444),
                ),
                const SizedBox(height: 20),
              ],
              
              if (_verificationStatus == 'pending') ...[
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Color(0xFF0D2E6E).withOpacity(0.3),
                        blurRadius: 8,
                        offset: Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.verified_user, color: Colors.white, size: 24),
                          SizedBox(width: 12),
                          Text('Complete KYC to Add Vehicles', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Colors.white)),
                        ],
                      ),
                      SizedBox(height: 12),
                      Text('Required for all vendors:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Colors.white)),
                      SizedBox(height: 8),
                      _buildRequirement('✓ Aadhaar Card (12 digits)'),
                      _buildRequirement('✓ PAN Card (10 characters)'),
                      _buildRequirement('✓ Passport Size Photo'),
                      _buildRequirement('✓ Bank Account Details'),
                      _buildRequirement('✓ Bank Account Photo'),
                      SizedBox(height: 8),
                      Text('Optional (for business accounts):', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Colors.white70)),
                      SizedBox(height: 4),
                      _buildRequirement('• GST Certificate', optional: true),
                      _buildRequirement('• Address Proof', optional: true),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
              ],
              
              // Account Type Selection
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0), width: 2),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.account_circle, color: Color(0xFF3B82F6), size: 24),
                        const SizedBox(width: 12),
                        const Text(
                          'Account Type',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'Select your account type',
                      style: TextStyle(
                        fontSize: 13,
                        color: Color(0xFF64748B),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              setState(() {
                                _accountType = 'individual';
                                _companyNameCtrl.clear();
                                _gstCtrl.clear();
                                _addressCtrl.clear();
                                _gstFile = null;
                                _addressProofFile = null;
                              });
                            },
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: _accountType == 'individual' 
                                  ? const Color(0xFF3B82F6) 
                                  : Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: _accountType == 'individual' 
                                    ? const Color(0xFF3B82F6) 
                                    : const Color(0xFFE2E8F0),
                                  width: 2,
                                ),
                              ),
                              child: Column(
                                children: [
                                  Icon(
                                    Icons.person,
                                    color: _accountType == 'individual' 
                                      ? Colors.white 
                                      : const Color(0xFF64748B),
                                    size: 32,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    'Individual',
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: _accountType == 'individual' 
                                        ? Colors.white 
                                        : const Color(0xFF0F172A),
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Personal use',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: _accountType == 'individual' 
                                        ? Colors.white70 
                                        : const Color(0xFF64748B),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              setState(() {
                                _accountType = 'business';
                                _businessDetailsExpanded = true;
                              });
                            },
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: _accountType == 'business' 
                                  ? const Color(0xFF10B981) 
                                  : Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: _accountType == 'business' 
                                    ? const Color(0xFF10B981) 
                                    : const Color(0xFFE2E8F0),
                                  width: 2,
                                ),
                              ),
                              child: Column(
                                children: [
                                  Icon(
                                    Icons.business,
                                    color: _accountType == 'business' 
                                      ? Colors.white 
                                      : const Color(0xFF64748B),
                                    size: 32,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    'Business',
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: _accountType == 'business' 
                                        ? Colors.white 
                                        : const Color(0xFF0F172A),
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Company use',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: _accountType == 'business' 
                                        ? Colors.white70 
                                        : const Color(0xFF64748B),
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
                ),
              ),
              
              const SizedBox(height: 16),
              
              _buildCollapsibleSection(
                title: '👤 Personal Details (Required)',
                isExpanded: _personalDetailsExpanded,
                onToggle: () => setState(() => _personalDetailsExpanded = !_personalDetailsExpanded),
                child: Column(
                  children: [
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _nameCtrl,
                      decoration: _inputDecoration('Full Name', 'Enter your full name', Icons.person),
                      textCapitalization: TextCapitalization.words,
                      validator: (val) => (val == null || val.isEmpty) ? 'Name is required' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _emailCtrl,
                      decoration: _inputDecoration('Email Address', 'your.email@example.com', Icons.email),
                      keyboardType: TextInputType.emailAddress,
                      validator: (val) {
                        if (val == null || val.isEmpty) return 'Email is required';
                        if (!val.contains('@')) return 'Enter a valid email';
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phoneCtrl,
                      decoration: _inputDecoration('Phone Number', '9876543210', Icons.phone),
                      keyboardType: TextInputType.phone,
                      maxLength: 10,
                      validator: (val) {
                        if (val == null || val.isEmpty) return 'Phone number is required';
                        if (val.length != 10) return 'Enter valid 10-digit phone number';
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _aadhaarCtrl,
                      decoration: _inputDecoration('Aadhaar Number', 'XXXX XXXX XXXX', Icons.credit_card),
                      keyboardType: TextInputType.number,
                      maxLength: 12,
                      validator: (val) => (val == null || val.length != 12) ? 'Enter valid 12-digit Aadhaar' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _panCtrl,
                      decoration: _inputDecoration('PAN Number', 'ABCDE1234F', Icons.badge),
                      maxLength: 10,
                      textCapitalization: TextCapitalization.characters,
                      validator: (val) => (val == null || val.length != 10) ? 'Enter valid 10-character PAN' : null,
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 16),
              
              _buildCollapsibleSection(
                title: _accountType == 'business' 
                  ? '🏢 Business Details (Required)' 
                  : '🏢 Business Details (Optional)',
                isExpanded: _businessDetailsExpanded,
                onToggle: () => setState(() => _businessDetailsExpanded = !_businessDetailsExpanded),
                child: Column(
                  children: [
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _companyNameCtrl,
                      decoration: _inputDecoration(
                        _accountType == 'business' ? 'Company Name *' : 'Company Name', 
                        'Your Company Pvt Ltd', 
                        Icons.business
                      ),
                      validator: _accountType == 'business' 
                        ? (val) => (val == null || val.isEmpty) ? 'Company name is required for business accounts' : null
                        : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _gstCtrl,
                      decoration: _inputDecoration(
                        _accountType == 'business' ? 'GST Number *' : 'GST Number (Optional)', 
                        '22AAAAA0000A1Z5', 
                        Icons.receipt_long
                      ),
                      maxLength: 15,
                      textCapitalization: TextCapitalization.characters,
                      validator: _accountType == 'business' 
                        ? (val) => (val == null || val.isEmpty) ? 'GST number is required for business accounts' : null
                        : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _addressCtrl,
                      decoration: _inputDecoration(
                        _accountType == 'business' ? 'Business Address *' : 'Business Address', 
                        'Full address', 
                        Icons.location_on
                      ),
                      maxLines: 3,
                      validator: _accountType == 'business' 
                        ? (val) => (val == null || val.isEmpty) ? 'Business address is required for business accounts' : null
                        : null,
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 16),
              
              _buildCollapsibleSection(
                title: '🏦 Bank Account Details (Required)',
                isExpanded: _bankDetailsExpanded,
                onToggle: () => setState(() => _bankDetailsExpanded = !_bankDetailsExpanded),
                child: Column(
                  children: [
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _bankAccountNameCtrl,
                      decoration: _inputDecoration('Account Holder Name *', 'As per bank records', Icons.account_circle),
                      textCapitalization: TextCapitalization.words,
                      validator: (val) => (val == null || val.isEmpty) ? 'Account holder name is required' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _bankAccountNumberCtrl,
                      decoration: _inputDecoration('Bank Account Number *', 'Enter account number', Icons.account_balance),
                      keyboardType: TextInputType.number,
                      validator: (val) {
                        if (val == null || val.isEmpty) return 'Account number is required';
                        if (val.length < 9 || val.length > 18) return 'Enter valid account number';
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _ifscCodeCtrl,
                      decoration: _inputDecoration('IFSC Code *', 'ABCD0123456', Icons.code),
                      maxLength: 11,
                      textCapitalization: TextCapitalization.characters,
                      validator: (val) {
                        if (val == null || val.isEmpty) return 'IFSC code is required';
                        if (val.length != 11) return 'Enter valid 11-character IFSC code';
                        return null;
                      },
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 16),
              
              _buildCollapsibleSection(
                title: '📎 Upload Documents',
                isExpanded: _documentsExpanded,
                onToggle: () => setState(() => _documentsExpanded = !_documentsExpanded),
                child: Column(
                  children: [
                    const SizedBox(height: 12),
                    _buildDocumentUpload('Aadhaar Card', 'Front & Back (Required)', _aadhaarFile, 'aadhaar', true, uploadedFileName: _uploadedAadhaar),
                    const SizedBox(height: 12),
                    _buildDocumentUpload('PAN Card', 'Clear photo (Required)', _panFile, 'pan', true, uploadedFileName: _uploadedPan),
                    const SizedBox(height: 12),
                    _buildDocumentUpload(
                      'GST Certificate', 
                      _accountType == 'business' 
                        ? 'Required for business accounts' 
                        : 'For business accounts (Optional)', 
                      _gstFile, 
                      'gst', 
                      _accountType == 'business',
                      uploadedFileName: _uploadedGst
                    ),
                    const SizedBox(height: 12),
                    _buildDocumentUpload(
                      'Address Proof', 
                      _accountType == 'business' 
                        ? 'Utility bill, Bank statement (Required)' 
                        : 'Utility bill, Bank statement (Optional)', 
                      _addressProofFile, 
                      'address', 
                      _accountType == 'business',
                      uploadedFileName: _uploadedAddress
                    ),
                    const SizedBox(height: 12),
                    _buildDocumentUpload('Passport Photo', 'Recent photo (Required)', _photoFile, 'photo', true, uploadedFileName: _uploadedPhoto),
                    const SizedBox(height: 12),
                    _buildDocumentUpload('Bank Account Photo', 'Passbook/Cheque (Required)', _bankAccountPhotoFile, 'bank_account', true, uploadedFileName: _uploadedBankAccountPhoto),
                  ],
                ),
              ),
              
              const SizedBox(height: 32),
              
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: _isReadyToSubmit() ? Color(0xFF10B981) : Color(0xFFE2E8F0),
                    width: 2,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          _isReadyToSubmit() ? Icons.check_circle : Icons.pending,
                          color: _isReadyToSubmit() ? Color(0xFF10B981) : Color(0xFFF59E0B),
                          size: 24,
                        ),
                        SizedBox(width: 12),
                        Text(
                          _isReadyToSubmit() ? 'Ready to Submit!' : 'Upload Required Documents',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: _isReadyToSubmit() ? Color(0xFF10B981) : Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 12),
                    _buildUploadStatus('Aadhaar Card', _aadhaarFile != null || _uploadedAadhaar != null),
                    _buildUploadStatus('PAN Card', _panFile != null || _uploadedPan != null),
                    _buildUploadStatus('Passport Photo', _photoFile != null || _uploadedPhoto != null),
                    _buildUploadStatus('Bank Account Photo', _bankAccountPhotoFile != null || _uploadedBankAccountPhoto != null),
                    if (_gstFile != null || _addressProofFile != null || _uploadedGst != null || _uploadedAddress != null) ...[
                      Divider(height: 20),
                      Text(
                        'Optional Documents:',
                        style: TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                      ),
                      SizedBox(height: 8),
                      if (_gstFile != null || _uploadedGst != null) _buildUploadStatus('GST Certificate', true),
                      if (_addressProofFile != null || _uploadedAddress != null) _buildUploadStatus('Address Proof', true),
                    ],
                  ],
                ),
              ),
              
              const SizedBox(height: 20),

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
                      onTap: () => _showTermsDialog(),
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

              const SizedBox(height: 20),

              SizedBox(
                width: double.infinity,
                height: 54,
                child: ElevatedButton(
                  onPressed: (_isSubmitting || (_verificationStatus == 'submitted' && _verificationStatus != 'rejected') || !_isReadyToSubmit() || !_agreedToTerms) ? null : _submitKYC,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF10B981),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: const Color(0xFF64748B),
                    disabledForegroundColor: Colors.white70,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : Text(
                          _verificationStatus == 'submitted' 
                              ? 'Verification Pending'
                              : _verificationStatus == 'rejected'
                                  ? 'Re-Submit for Verification'
                                  : _isReadyToSubmit()
                                      ? 'Submit for Verification'
                                      : 'Upload All Required Documents First',
                          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                ),
              ),
              
              const SizedBox(height: 16),
              
              Center(
                child: TextButton.icon(
                  onPressed: () => context.go('/vendor/help'),
                  icon: const Icon(Icons.help_outline, size: 18),
                  label: const Text('Need help with KYC?'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label, String hint, IconData icon) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon, size: 20),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      filled: true,
      fillColor: Colors.white,
      counterText: '',
    );
  }

  Widget _buildDocumentUpload(String title, String subtitle, PlatformFile? file, String docType, bool required, {String? uploadedFileName}) {
    final isUploaded = file != null || uploadedFileName != null;
    final displayName = file?.name ?? uploadedFileName;
    
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isUploaded ? const Color(0xFF10B981) : const Color(0xFFE2E8F0),
          width: 2,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: isUploaded ? const Color(0xFFD1FAE5) : const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              isUploaded ? Icons.check_circle : Icons.upload_file,
              color: isUploaded ? const Color(0xFF10B981) : const Color(0xFF64748B),
              size: 24,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                    if (required) ...[
                      const SizedBox(width: 4),
                      const Text('*', style: TextStyle(color: Color(0xFFEF4444), fontSize: 14)),
                    ],
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  isUploaded ? (displayName ?? subtitle) : subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: isUploaded ? const Color(0xFF10B981) : const Color(0xFF64748B),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: () => _pickFile(docType),
            style: ElevatedButton.styleFrom(
              backgroundColor: isUploaded ? const Color(0xFF10B981) : const Color(0xFF3B82F6),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: Text(
              isUploaded ? 'Change' : 'Upload',
              style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCollapsibleSection({
    required String title,
    required bool isExpanded,
    required VoidCallback onToggle,
    required Widget child,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0), width: 1),
      ),
      child: Column(
        children: [
          InkWell(
            onTap: onToggle,
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF1E293B),
                      ),
                    ),
                  ),
                  Icon(
                    isExpanded ? Icons.expand_less : Icons.expand_more,
                    color: const Color(0xFF64748B),
                  ),
                ],
              ),
            ),
          ),
          if (isExpanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: child,
            ),
        ],
      ),
    );
  }

  Widget _buildStatusBanner(String title, String message, Color bgColor, Color borderColor) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor, width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: borderColor),
          ),
          const SizedBox(height: 4),
          Text(
            message,
            style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
          ),
        ],
      ),
    );
  }

  Widget _buildRequirement(String text, {bool optional = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 12, 
          color: optional ? Colors.white60 : Colors.white, 
          fontWeight: FontWeight.w500
        ),
      ),
    );
  }

  bool _isReadyToSubmit() {
    // Basic required documents and personal details
    bool basicDocsReady = (_aadhaarFile != null || _uploadedAadhaar != null) && 
           (_panFile != null || _uploadedPan != null) && 
           (_photoFile != null || _uploadedPhoto != null) &&
           (_bankAccountPhotoFile != null || _uploadedBankAccountPhoto != null) &&
           _nameCtrl.text.isNotEmpty &&
           _emailCtrl.text.isNotEmpty &&
           _phoneCtrl.text.length == 10 &&
           _aadhaarCtrl.text.length == 12 &&
           _panCtrl.text.length == 10 &&
           _bankAccountNameCtrl.text.isNotEmpty &&
           _bankAccountNumberCtrl.text.isNotEmpty &&
           _ifscCodeCtrl.text.length == 11;
    
    // Additional validation for business accounts
    if (_accountType == 'business') {
      return basicDocsReady &&
             (_gstFile != null || _uploadedGst != null) &&
             (_addressProofFile != null || _uploadedAddress != null) &&
             _companyNameCtrl.text.isNotEmpty &&
             _gstCtrl.text.isNotEmpty &&
             _addressCtrl.text.isNotEmpty;
    }
    
    return basicDocsReady;
  }

  Widget _buildUploadStatus(String label, bool isUploaded) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Icon(
            isUploaded ? Icons.check_circle : Icons.radio_button_unchecked,
            color: isUploaded ? Color(0xFF10B981) : Color(0xFFE2E8F0),
            size: 20,
          ),
          SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              color: isUploaded ? Color(0xFF10B981) : Color(0xFF64748B),
              fontWeight: isUploaded ? FontWeight.w600 : FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  void _showTermsDialog() {
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
                gradient: LinearGradient(colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)]),
                borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.gavel, color: Colors.white, size: 24),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text('Vendor Terms & Conditions',
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
                  '''1. Acceptance of Terms\nBy registering and using this platform, you agree to comply with all company policies, procedures, and applicable laws.\n\n2. Accurate Information\nAll business, tax, bank, and contact information provided must be accurate and up to date.\n\n3. Compliance with Laws\nYou shall comply with all applicable laws, regulations, licenses, permits, and statutory requirements.\n\n4. Quality Standards\nGoods and services must meet the quality standards, specifications, and requirements agreed with the company.\n\n5. Timely Delivery\nYou agree to deliver goods or services within the agreed timelines.\n\n6. Pricing and Payments\nYou shall honor the agreed pricing and payment terms approved by the company.\n\n7. Confidentiality\nAll company information, customer details, pricing, and business data must be kept confidential.\n\n8. No Fraudulent Activities\nYou shall not engage in fraudulent practices, bribery, corruption, misrepresentation, or unethical conduct.\n\n9. Document Verification\nThe company reserves the right to verify your documents, licenses, certifications, and other information at any time.\n\n10. Platform Usage\nThe platform shall only be used for authorized business purposes.\n\n11. Account Security\nYou are responsible for maintaining the confidentiality of login credentials, passwords, and OTPs.\n\n12. Audit Rights\nThe company reserves the right to review your activities, transactions, and records related to services provided.\n\n13. Suspension or Termination\nAccess may be suspended or terminated for policy violations, poor performance, fraudulent activities, or non-compliance.\n\n14. Indemnification\nYou shall be responsible for any losses, damages, claims, or liabilities arising from your actions, negligence, or breach of these terms.\n\n15. Modification of Terms\nThe company reserves the right to amend these Terms & Conditions at any time. Continued use constitutes acceptance of revised terms.''',
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
                        backgroundColor: const Color(0xFF10B981),
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

  void _showSnackBar(String message, {bool isError = true}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              isError ? Icons.error_outline : Icons.check_circle_outline,
              color: Colors.white,
              size: 20,
            ),
            const SizedBox(width: 8),
            Expanded(child: Text(message)),
          ],
        ),
        backgroundColor: isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
      ),
    );
  }
}
