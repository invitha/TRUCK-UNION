import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:go_router/go_router.dart';
import '../../config/app_theme.dart';
import '../../services/api_service.dart';

class AddVehicleScreen extends StatefulWidget {
  const AddVehicleScreen({super.key});

  @override
  State<AddVehicleScreen> createState() => _AddVehicleScreenState();
}

class _AddVehicleScreenState extends State<AddVehicleScreen> {
  final _formKey = GlobalKey<FormState>();
  final _vendorNameController = TextEditingController();
  final _vendorEmailController = TextEditingController();
  final _vendorPhoneController = TextEditingController();
  final _vendorLocationController = TextEditingController();
  
  List<VehicleForm> _vehicleForms = [VehicleForm()];
  bool _isSubmitting = false;
  
  final List<String> _vehicleTypes = [
    'Mini Truck',
    'Light Truck',
    'Medium Truck',
    'Heavy Truck',
    'Trailer',
    'Container',
    'Tanker',
    'Refrigerated',
    'Flatbed',
    'Other (Specify)'
  ];
  
  final List<String> _vehicleSizes = [
    '6 feet',
    '7 feet',
    '10 feet',
    '12 feet',
    '14 feet',
    '17 feet',
    '19 feet',
    '20 feet',
    '22 feet',
    '24 feet',
    '32 feet',
    '40 feet',
    'Other (Specify)'
  ];
  
  final List<String> _vehicleTonnages = [
    '1 Ton',
    '2 Ton',
    '3 Ton',
    '5 Ton',
    '7 Ton',
    '10 Ton',
    '12 Ton',
    '15 Ton',
    '20 Ton',
    '25 Ton',
    '30 Ton',
    'Other (Specify)'
  ];

  @override
  void initState() {
    super.initState();
    _loadVendorInfo();
  }

  Future<void> _loadVendorInfo() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      setState(() {
        _vendorEmailController.text = user.email ?? '';
      });
      
      // Try to load from KYC data
      try {
        final response = await ApiService.getKYCStatus(firebaseUid: user.uid);
        if (response['status'] == 'success' && response['kyc_data'] != null) {
          setState(() {
            _vendorNameController.text = response['kyc_data']['name'] ?? '';
            _vendorPhoneController.text = response['kyc_data']['phone'] ?? '';
            // Location will be entered manually by vendor
          });
        }
      } catch (e) {
        print('Error loading vendor info: $e');
      }
    }
  }

  void _addVehicleForm() {
    setState(() {
      _vehicleForms.add(VehicleForm());
    });
  }

  void _removeVehicleForm(int index) {
    if (_vehicleForms.length > 1) {
      setState(() {
        _vehicleForms.removeAt(index);
      });
    }
  }

  Future<void> _submitVehicles() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final user = FirebaseAuth.instance.currentUser;
    if (user == null) {
      _showErrorDialog('Please login to continue');
      return;
    }

    // Validate custom fields if "Other" is selected
    for (int i = 0; i < _vehicleForms.length; i++) {
      final form = _vehicleForms[i];
      if (form.selectedType == 'Other (Specify)' && form.customTypeController.text.trim().isEmpty) {
        _showErrorDialog('Please specify vehicle type for Vehicle ${i + 1}');
        return;
      }
      if (form.selectedSize == 'Other (Specify)' && form.customSizeController.text.trim().isEmpty) {
        _showErrorDialog('Please specify vehicle size for Vehicle ${i + 1}');
        return;
      }
      if (form.selectedTonnage == 'Other (Specify)' && form.customTonnageController.text.trim().isEmpty) {
        _showErrorDialog('Please specify vehicle tonnage for Vehicle ${i + 1}');
        return;
      }
    }

    setState(() => _isSubmitting = true);

    try {
      final vehicles = _vehicleForms.map((form) {
        final vehicleType = form.selectedType == 'Other (Specify)' 
            ? form.customTypeController.text.trim() 
            : form.selectedType;
        final vehicleSize = form.selectedSize == 'Other (Specify)' 
            ? form.customSizeController.text.trim() 
            : form.selectedSize;
        final vehicleTonnage = form.selectedTonnage == 'Other (Specify)' 
            ? form.customTonnageController.text.trim() 
            : form.selectedTonnage;
            
        return {
          'vehicle_number': form.vehicleNumberController.text.trim(),
          'vehicle_name': form.vehicleNameController.text.trim(),
          'vehicle_year': form.vehicleYearController.text.trim(),
          'vehicle_type': vehicleType,
          'vehicle_size_feet': vehicleSize,
          'vehicle_tonnage': vehicleTonnage,
          'driver_name': form.driverNameController.text.trim(),
          'driver_phone': form.driverPhoneController.text.trim(), // NEW
          'driver_username': form.driverUsernameController.text.trim(),
          'driver_password': form.driverPasswordController.text.trim(),
        };
      }).toList();

      final response = await ApiService.addVehicles(
        firebaseUid: user.uid,
        vendorName: _vendorNameController.text.trim(),
        vendorEmail: _vendorEmailController.text.trim(),
        vendorPhone: _vendorPhoneController.text.trim(),
        vendorLocation: _vendorLocationController.text.trim(),
        vehicles: vehicles,
      );

      setState(() => _isSubmitting = false);

      if (response['status'] == 'success') {
        if (mounted) {
          _showSuccessDialog(response['message'] ?? 'Vehicles added successfully');
        }
      } else {
        _showErrorDialog(response['message'] ?? 'Failed to add vehicles');
      }
    } catch (e) {
      setState(() => _isSubmitting = false);
      _showErrorDialog('Error: $e');
    }
  }

  void _showSuccessDialog(String message) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.green,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.check_circle, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 12),
            const Text('Success', style: TextStyle(fontWeight: FontWeight.w700)),
          ],
        ),
        content: Text(message),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              context.go('/vendor/vehicles');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primaryBlue,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text('View My Vehicles', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.red,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.error, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 12),
            const Text('Error', style: TextStyle(fontWeight: FontWeight.w700)),
          ],
        ),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.primaryBlue),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/vendor/vehicles');
            }
          },
        ),
        title: const Text(
          'Add Vehicles',
          style: TextStyle(
            color: AppTheme.primaryBlue,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Vendor Information Section removed - now handled automatically by the backend


              // Vehicles Section
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Vehicles',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  ElevatedButton.icon(
                    onPressed: _addVehicleForm,
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Add Vehicle'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryBlue,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 16),

              // Vehicle Forms
              ..._vehicleForms.asMap().entries.map((entry) {
                final index = entry.key;
                final form = entry.value;
                return _buildVehicleCard(form, index);
              }).toList(),

              const SizedBox(height: 24),

              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submitVehicles,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryBlue,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        )
                      : Text(
                          'Submit ${_vehicleForms.length} Vehicle(s)',
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),

              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildVehicleCard(VehicleForm form, int index) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primaryBlue.withOpacity(0.2), width: 2),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Vehicle ${index + 1}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: AppTheme.primaryBlue,
                ),
              ),
              if (_vehicleForms.length > 1)
                IconButton(
                  onPressed: () => _removeVehicleForm(index),
                  icon: const Icon(Icons.delete, color: Colors.red),
                  tooltip: 'Remove Vehicle',
                ),
            ],
          ),
          const SizedBox(height: 16),
          
          _buildTextField(
            controller: form.vehicleNumberController,
            label: 'Vehicle Number',
            icon: Icons.confirmation_number,
            textCapitalization: TextCapitalization.characters,
            validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          
          _buildTextField(
            controller: form.vehicleNameController,
            label: 'Vehicle Name/Model',
            icon: Icons.local_shipping,
            validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          
          _buildTextField(
            controller: form.vehicleYearController,
            label: 'Vehicle Year',
            icon: Icons.calendar_today,
            keyboardType: TextInputType.number,
            validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          
          _buildDropdown(
            value: form.selectedType,
            label: 'Vehicle Type',
            icon: Icons.category,
            items: _vehicleTypes,
            onChanged: (value) => setState(() => form.selectedType = value!),
          ),
          if (form.selectedType == 'Other (Specify)') ...[
            const SizedBox(height: 12),
            _buildTextField(
              controller: form.customTypeController,
              label: 'Specify Vehicle Type',
              icon: Icons.edit,
              validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
            ),
          ],
          const SizedBox(height: 12),
          
          _buildDropdown(
            value: form.selectedSize,
            label: 'Vehicle Size',
            icon: Icons.straighten,
            items: _vehicleSizes,
            onChanged: (value) => setState(() => form.selectedSize = value!),
          ),
          if (form.selectedSize == 'Other (Specify)') ...[
            const SizedBox(height: 12),
            _buildTextField(
              controller: form.customSizeController,
              label: 'Specify Vehicle Size (e.g., 15 feet)',
              icon: Icons.edit,
              validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
            ),
          ],
          const SizedBox(height: 12),
          
          _buildDropdown(
            value: form.selectedTonnage,
            label: 'Vehicle Tonnage',
            icon: Icons.fitness_center,
            items: _vehicleTonnages,
            onChanged: (value) => setState(() => form.selectedTonnage = value!),
          ),
          if (form.selectedTonnage == 'Other (Specify)') ...[
            const SizedBox(height: 12),
            _buildTextField(
              controller: form.customTonnageController,
              label: 'Specify Vehicle Tonnage (e.g., 8 Ton)',
              icon: Icons.edit,
              validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
            ),
          ],
          
          const SizedBox(height: 20),
          const Divider(),
          const SizedBox(height: 12),
          
          const Text(
            'Driver Information',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          
          _buildTextField(
            controller: form.driverNameController,
            label: 'Driver Name',
            icon: Icons.person_outline,
            validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          
          _buildTextField(
            controller: form.driverPhoneController,
            label: 'Driver Phone Number',
            icon: Icons.phone,
            keyboardType: TextInputType.phone,
            validator: (value) => value?.isEmpty ?? true ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          
          // Auto-generated username (visible but not editable) - Shows vehicle number
          TextFormField(
            controller: form.driverUsernameController,
            enabled: false, // Makes it read-only
            decoration: InputDecoration(
              labelText: 'Driver Username',
              hintText: 'Enter vehicle number above to generate',
              prefixIcon: const Icon(Icons.account_circle, color: AppTheme.primaryBlue),
              suffixIcon: Container(
                margin: const EdgeInsets.all(8),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF0D2E6E), Color(0xFF1E40AF)],
                  ),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.auto_awesome, color: Colors.white, size: 14),
                    SizedBox(width: 4),
                    Text(
                      'AUTO',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.blue.withOpacity(0.3)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.blue.withOpacity(0.3)),
              ),
              disabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.blue.withOpacity(0.3)),
              ),
              filled: true,
              fillColor: Colors.blue.withOpacity(0.05),
            ),
            style: const TextStyle(
              color: AppTheme.textPrimary,
              fontWeight: FontWeight.w700,
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 12),
          
          _buildTextField(
            controller: form.driverPasswordController,
            label: 'Driver Password (Set by Vendor)',
            icon: Icons.lock,
            obscureText: true,
            validator: (value) {
              if (value?.isEmpty ?? true) return 'Required';
              if (value!.length < 6) return 'Password must be at least 6 characters';
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    TextInputType? keyboardType,
    bool obscureText = false,
    TextCapitalization textCapitalization = TextCapitalization.none,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      textCapitalization: textCapitalization,
      obscureText: obscureText,
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: AppTheme.primaryBlue),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: AppTheme.borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: AppTheme.borderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppTheme.primaryBlue, width: 2),
        ),
        filled: true,
        fillColor: Colors.grey[50],
      ),
    );
  }

  Widget _buildDropdown({
    required String value,
    required String label,
    required IconData icon,
    required List<String> items,
    required void Function(String?) onChanged,
  }) {
    return DropdownButtonFormField<String>(
      value: value,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: AppTheme.primaryBlue),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: AppTheme.borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: AppTheme.borderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppTheme.primaryBlue, width: 2),
        ),
        filled: true,
        fillColor: Colors.grey[50],
      ),
      items: items.map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
      onChanged: onChanged,
    );
  }

  @override
  void dispose() {
    _vendorNameController.dispose();
    _vendorEmailController.dispose();
    _vendorPhoneController.dispose();
    _vendorLocationController.dispose();
    for (var form in _vehicleForms) {
      form.dispose();
    }
    super.dispose();
  }
}

class VehicleForm {
  final vehicleNumberController = TextEditingController();
  final vehicleNameController = TextEditingController();
  final vehicleYearController = TextEditingController();
  String selectedType = 'Mini Truck';
  String selectedSize = '6 feet';
  String selectedTonnage = '1 Ton';
  final customTypeController = TextEditingController();
  final customSizeController = TextEditingController();
  final customTonnageController = TextEditingController();
  final driverNameController = TextEditingController();
  final driverPhoneController = TextEditingController(); // NEW: Driver phone
  final driverUsernameController = TextEditingController(); // Auto-generated, read-only
  final driverPasswordController = TextEditingController();

  VehicleForm() {
    // Listen to vehicle number changes to auto-generate username
    vehicleNumberController.addListener(_generateUsername);
  }

  void _generateUsername() {
    // Generate username from vehicle number (lowercase, no spaces, no special chars)
    final vehicleNumber = vehicleNumberController.text.trim();
    if (vehicleNumber.isNotEmpty) {
      final username = vehicleNumber
          .toLowerCase()
          .replaceAll(RegExp(r'[^a-z0-9]'), '') // Remove special chars and spaces
          .replaceAll(RegExp(r'\s+'), ''); // Remove any remaining spaces
      driverUsernameController.text = username;
    } else {
      driverUsernameController.text = '';
    }
  }

  void dispose() {
    vehicleNumberController.removeListener(_generateUsername);
    vehicleNumberController.dispose();
    vehicleNameController.dispose();
    vehicleYearController.dispose();
    customTypeController.dispose();
    customSizeController.dispose();
    customTonnageController.dispose();
    driverNameController.dispose();
    driverPhoneController.dispose();
    driverUsernameController.dispose();
    driverPasswordController.dispose();
  }
}
