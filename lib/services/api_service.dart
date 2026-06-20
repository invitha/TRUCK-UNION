import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

class ApiService {
  static const String baseUrl = 'https://crm.abra-logistic.com/api1';
  static final Dio _dio = Dio(
    BaseOptions(
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      validateStatus: (status) => status! < 500,
      followRedirects: true,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ),
  );

  static final Dio _multipartDio = Dio(
    BaseOptions(
      connectTimeout: const Duration(seconds: 60),
      receiveTimeout: const Duration(seconds: 120), // photos can be large
      sendTimeout: const Duration(seconds: 120),
      validateStatus: (status) => true, // accept ALL status codes — handle errors in code
      followRedirects: true,
      headers: {
        'Accept': 'application/json',
      },
    ),
  );

  // Check if user exists and their role
  static Future<Map<String, dynamic>> checkUserRole({
    required String firebaseUid,
    required String email,
    String? name,
  }) async {
    try {
      print('🔵 Checking user role...');
      print('🔵 Firebase UID: $firebaseUid');
      print('🔵 Email: $email');
      
      final response = await _dio.post(
        '$baseUrl/check_role.php',
        data: {
          'firebase_uid': firebaseUid,
          'email': email,
          'name': name ?? '',
        },
      );

      print('🟢 Role check response: ${response.statusCode}');
      print('🟢 Role check data: ${response.data}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Role check error: $e');
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Update user role to vendor
  static Future<Map<String, dynamic>> updateUserRole({
    required String firebaseUid,
    required String email,
    String? name,
    required String role,
  }) async {
    try {
      print('🔵 Updating user role to: $role');
      
      final response = await _dio.post(
        '$baseUrl/update_role.php',
        data: {
          'firebase_uid': firebaseUid,
          'email': email,
          'name': name ?? '',
          'role': role,
        },
      );

      print('🟢 Update response: ${response.data}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Update error: $e');
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Get vendor KYC status
  static Future<Map<String, dynamic>> getKYCStatus({
    required String firebaseUid,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/get_kyc_status.php',
        data: {'firebase_uid': firebaseUid},
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Check if KYC details already exist (for duplicate validation)
  static Future<Map<String, dynamic>> checkKYCExists({
    required String firebaseUid,
    String? aadhaarNumber,
    String? panNumber,
    String? bankAccountNumber,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/check_kyc_exists.php',
        data: {
          'firebase_uid': firebaseUid,
          if (aadhaarNumber != null) 'aadhaar_number': aadhaarNumber,
          if (panNumber != null) 'pan_number': panNumber,
          if (bankAccountNumber != null) 'bank_account_number': bankAccountNumber,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Submit vendor KYC
  static Future<Map<String, dynamic>> submitKYC({
    required String firebaseUid,
    required String aadhaarNumber,
    required String panNumber,
    String? gstNumber,
    String? companyName,
    String? address,
    required Map<String, dynamic> documents,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/submit_kyc.php',
        data: {
          'firebase_uid': firebaseUid,
          'aadhaar_number': aadhaarNumber,
          'pan_number': panNumber,
          'gst_number': gstNumber ?? '',
          'company_name': companyName ?? '',
          'address': address ?? '',
          'documents': documents,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Get vendor vehicles
  static Future<Map<String, dynamic>> getVendorVehicles({
    required String firebaseUid,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/get_vehicles.php',
        data: {'firebase_uid': firebaseUid},
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Add single vehicle
  static Future<Map<String, dynamic>> addVehicle({
    required String firebaseUid,
    required String vehicleNumber,
    required String vehicleType,
    required String capacity,
    required String driverName,
    required String driverPhone,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/add_vehicle.php',
        data: {
          'firebase_uid': firebaseUid,
          'vehicle_number': vehicleNumber,
          'vehicle_type': vehicleType,
          'capacity': capacity,
          'driver_name': driverName,
          'driver_phone': driverPhone,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Bulk upload vehicles
  static Future<Map<String, dynamic>> bulkUploadVehicles({
    required String firebaseUid,
    required String fileData,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/bulk_upload_vehicles.php',
        data: {
          'firebase_uid': firebaseUid,
          'file_data': fileData,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Get vendor dashboard stats
  static Future<Map<String, dynamic>> getVendorStats({
    required String firebaseUid,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/get_stats.php',
        data: {'firebase_uid': firebaseUid},
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Save FCM token for push notifications
  static Future<void> saveFcmToken({
    required String firebaseUid,
    required String fcmToken,
    String platform = 'android',
  }) async {
    try {
      await _dio.post(
        '$baseUrl/vendor/save_fcm_token.php',
        data: {'firebase_uid': firebaseUid, 'fcm_token': fcmToken, 'platform': platform},
      );
    } catch (_) {
      // Non-critical — fail silently
    }
  }

  // Get notifications
  static Future<Map<String, dynamic>> getNotifications({
    required String firebaseUid,
    required int timestamp,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/get_notifications.php',
        data: {
          'firebase_uid': firebaseUid,
          'timestamp': timestamp,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e', 'notifications': [], 'unread_count': 0};
    }
  }

  // Mark notification as read
  static Future<Map<String, dynamic>> markNotificationRead({
    required String firebaseUid,
    int? notificationId,
    bool markAll = false,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/mark_notification_read.php',
        data: {
          'firebase_uid': firebaseUid,
          if (notificationId != null) 'notification_id': notificationId,
          'mark_all': markAll ? 1 : 0,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Error: $e'};
    }
  }

  // Vehicle Management Methods
  static Future<Map<String, dynamic>> addVehicles({
    required String firebaseUid,
    required String vendorName,
    required String vendorEmail,
    required String vendorPhone,
    required String vendorLocation,
    required List<Map<String, dynamic>> vehicles,
  }) async {
    try {
      print('🔵 Adding vehicles...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/add_vehicle.php',
        data: {
          'firebase_uid': firebaseUid,
          'vendor_name': vendorName,
          'vendor_email': vendorEmail,
          'vendor_phone': vendorPhone,
          'vendor_location': vendorLocation,
          'vehicles': vehicles,
        },
      );

      print('🟢 Add vehicles response: ${response.statusCode}');
      print('🟢 Add vehicles data: ${response.data}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error adding vehicles: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> getVehicles({
    required String firebaseUid,
  }) async {
    try {
      print('🔵 Getting vehicles...');
      
      // Using get_vehicle.php (singular) to match server filename
      final response = await _dio.post(
        '$baseUrl/vendor/get_vehicle.php',
        data: {'firebase_uid': firebaseUid},
      );

      print('🟢 Get vehicles response: ${response.statusCode}');
      print('🟢 Get vehicles data: ${response.data}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error getting vehicles: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> updateVehicle({
    required int id,
    required String firebaseUid,
    required String vehicleNumber,
    required String vehicleName,
    required String vehicleYear,
    required String vehicleType,
    required String vehicleSizeFeet,
    required String driverName,
    required String driverPhone,
    required String driverUsername,
    required String driverPassword,
  }) async {
    try {
      print('🔵 Updating vehicle...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/update_vehicle.php',
        data: {
          'id': id,
          'firebase_uid': firebaseUid,
          'vehicle_number': vehicleNumber,
          'vehicle_name': vehicleName,
          'vehicle_year': vehicleYear,
          'vehicle_type': vehicleType,
          'vehicle_size_feet': vehicleSizeFeet,
          'driver_name': driverName,
          'driver_phone': driverPhone,
          'driver_username': driverUsername,
          'driver_password': driverPassword,
        },
      );

      print('🟢 Update vehicle response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error updating vehicle: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> deleteVehicle({
    required int id,
    required String firebaseUid,
  }) async {
    try {
      print('🔵 Deleting vehicle...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/delete_vehicle.php',
        data: {
          'id': id,
          'firebase_uid': firebaseUid,
        },
      );

      print('🟢 Delete vehicle response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error deleting vehicle: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Driver Authentication
  static Future<Map<String, dynamic>> driverLogin({
    required String username,
    required String password,
  }) async {
    try {
      print('🔵 Driver login...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/driver_login.php',
        data: {
          'driver_username': username,
          'driver_password': password,
        },
      );

      print('🟢 Driver login response: ${response.statusCode}');
      print('🟢 Driver login data: ${response.data}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error driver login: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Driver Orders
  static Future<Map<String, dynamic>> getDriverOrders({
    required int vehicleId,
    String? statusFilter,
  }) async {
    try {
      print('🔵 Getting driver orders...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/get_driver_orders.php',
        data: {
          'vehicle_id': vehicleId,
          if (statusFilter != null) 'status': statusFilter,
        },
      );

      print('🟢 Get driver orders response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error getting driver orders: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> updateOrderStatus({
    required int orderId,
    required int vehicleId,
    required String status,
  }) async {
    try {
      print('🔵 Updating order status...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/update_order_status.php',
        data: {
          'order_id': orderId,
          'vehicle_id': vehicleId,
          'status': status,
        },
      );

      print('🟢 Update order status response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error updating order status: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> updateDriverLocation({
    required int vehicleId,
    required double latitude,
    required double longitude,
    String? address,
    int isOnline = 1,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/update_driver_location.php',
        data: {
          'vehicle_id': vehicleId,
          'latitude': latitude,
          'longitude': longitude,
          'address': address ?? '',
          'is_online': isOnline,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> setDriverOffline({
    required int vehicleId,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/update_driver_location.php',
        data: {
          'vehicle_id': vehicleId,
          'is_online': 0,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Fleet Assignments
  static Future<Map<String, dynamic>> getFleetAssignments({
    required String firebaseUid,
    String? statusFilter,
  }) async {
    try {
      print('🔵 Getting fleet assignments...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/get_fleet_assignments.php',
        data: {
          'firebase_uid': firebaseUid,
          if (statusFilter != null) 'status': statusFilter,
        },
      );

      print('🟢 Get fleet assignments response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error getting fleet assignments: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Shipment History / Timeline
  static Future<Map<String, dynamic>> getShipmentHistory({
    required String tracking,
  }) async {
    try {
      final response = await _dio.get(
        '$baseUrl/vendor/get_payment_history.php',
        queryParameters: {'tracking': tracking},
      );
      if (response.statusCode == 200) {
        final data = response.data is String ? jsonDecode(response.data) : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> updateFleetAssignmentStatus({
    required String assignmentId,
    required String firebaseUid,
    required String status,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/update_assignment_status.php',
        data: {
          'assignment_id': assignmentId,
          'firebase_uid': firebaseUid,
          'status': status,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Customer Orders (for reference)
  static Future<Map<String, dynamic>> getCustomerOrders({
    required String firebaseUid,
    String? customerEmail,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/customer/get_orders.php',
        data: {
          'firebase_uid': firebaseUid,
          if (customerEmail != null) 'customer_email': customerEmail,
        },
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Enhanced Driver Orders with AL Numbers and Load Types
  static Future<Map<String, dynamic>> getDriverOrdersEnhanced({
    required int vehicleId,
    String orderType = 'active', // 'active' | 'completed'
  }) async {
    try {
      print('🔵 Getting enhanced driver orders (type=$orderType)...');

      final response = await _dio.get(
        '$baseUrl/vendor/get_driver_orders_enhanced.php',
        queryParameters: {
          'vehicle_id': vehicleId,
          'order_type': orderType,
        },
      );

      print('🟢 Get enhanced driver orders response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error getting enhanced driver orders: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Enhanced Order Status Update with AL Numbers
  static Future<Map<String, dynamic>> updateOrderStatusEnhanced({
    required String alNumber,
    required int vehicleId,
    required String status,
    String? driverNotes,
    double? latitude,
    double? longitude,
  }) async {
    try {
      print('🔵 Updating order status (AL: $alNumber)...');
      
      final response = await _dio.post(
        '$baseUrl/vendor/update_order_status_enhanced.php',
        data: {
          'al_number': alNumber,
          'vehicle_id': vehicleId,
          'status': status,
          if (driverNotes != null) 'driver_notes': driverNotes,
          if (latitude != null) 'latitude': latitude,
          if (longitude != null) 'longitude': longitude,
        },
      );

      print('🟢 Update order status enhanced response: ${response.statusCode}');

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error updating order status enhanced: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Upload POD (Proof of Delivery) Image - Legacy method kept for compatibility
  static Future<Map<String, dynamic>> uploadPOD({
    required String alNumber,
    required int vehicleId,
    required String podType,
    required XFile imageFile,
    double? latitude,
    double? longitude,
  }) async {
    try {
      print('🔵 Uploading POD image (legacy)...');
      final bytes = await imageFile.readAsBytes();
      final filename = 'pod_${alNumber}_${podType}_${DateTime.now().millisecondsSinceEpoch}.jpg';
      FormData formData = FormData.fromMap({
        'al_number': alNumber,
        'vehicle_id': vehicleId,
        'pod_type': podType,
        'pod_image': MultipartFile.fromBytes(bytes, filename: filename),
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      });
      final response = await _multipartDio.post('$baseUrl/vendor/upload_pod.php', data: formData);
      if (response.statusCode == 200) {
        final data = response.data is String ? jsonDecode(response.data) : response.data;
        return data;
      }
      return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Upload Pickup POD - matches abra_logistics pickup-status endpoint
  static Future<Map<String, dynamic>> uploadPickupPOD({
    required String tracking,
    required String alNumber,
    required String vehicleId,
    required String pickupDriverId,
    required XFile podPhoto,
    required double latitude,
    required double longitude,
    String status = 'Picked Up',
    String? reason,
  }) async {
    try {
      print('🔵 Uploading pickup POD...');

      final bytes = await podPhoto.readAsBytes();
      final filename = 'pickup_${alNumber}_${DateTime.now().millisecondsSinceEpoch}.jpg';

      FormData formData = FormData.fromMap({
        'tracking': tracking,
        'al_number': alNumber,
        'vehicle_id': vehicleId,
        'pickupDriverId': pickupDriverId,
        'status': status,
        'latitude': latitude,
        'longitude': longitude,
        if (reason != null) 'reason': reason,
        'pickupPhoto': MultipartFile.fromBytes(bytes, filename: filename),
      });

      print('🔵 Pickup POD URL: $baseUrl/vendor/pickup_status.php');
      print('🔵 Pickup POD fields: tracking=$tracking, alNumber=$alNumber, vehicleId=$vehicleId, driverId=$pickupDriverId');

      final response = await _multipartDio.post(
        '$baseUrl/vendor/pickup_status.php',
        data: formData,
      );

      print('🟢 Upload pickup POD status: ${response.statusCode}');
      print('🟢 Upload pickup POD body: ${response.data}');

      if (response.statusCode == 200 || response.statusCode == 500) {
        // Parse even 500s — our PHP now returns JSON for all errors
        final data = response.data is String
            ? jsonDecode(response.data)
            : response.data;

        // Guard: wrong endpoint leak
        if (data is Map &&
            data['status'] == 'error' &&
            (data['message'] ?? '').toString().contains('receiverName')) {
          return {
            'status': 'error',
            'message': 'Server config error: wrong endpoint. Contact support.',
          };
        }

        return data is Map ? Map<String, dynamic>.from(data) : {'status': 'error', 'message': 'Unexpected response'};
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode} — ${response.data}'};
      }
    } catch (e) {
      print('🔴 Error uploading pickup POD: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Upload Delivery POD - matches abra_logistics pod endpoint
  static Future<Map<String, dynamic>> uploadDeliveryPOD({
    required String tracking,
    required String alNumber,
    required String vehicleId,
    required String deliveryDriverId,
    required XFile podPhoto,
    required String receiverName,
    required String receiverPhoneNumber,
    String? scannedBarcode,
    required double latitude,
    required double longitude,
  }) async {
    try {
      print('🔵 Uploading delivery POD...');

      final bytes = await podPhoto.readAsBytes();
      final filename = 'delivery_${alNumber}_${DateTime.now().millisecondsSinceEpoch}.jpg';

      FormData formData = FormData.fromMap({
        'tracking': tracking,
        'al_number': alNumber,
        'vehicle_id': vehicleId,
        'deliveryDriverId': deliveryDriverId,
        'receiverName': receiverName,
        'receiverPhoneNumber': receiverPhoneNumber,
        if (scannedBarcode != null) 'scannedBarcode': scannedBarcode,
        'latitude': latitude,
        'longitude': longitude,
        'PODPhoto': MultipartFile.fromBytes(bytes, filename: filename),
      });

      final response = await _multipartDio.post(
        '$baseUrl/vendor/delivery_pod.php',
        data: formData,
      );

      print('🟢 Upload delivery POD response: ${response.statusCode}');
      print('🟢 Upload delivery POD body: ${response.data}');

      if (response.statusCode == 200 || response.statusCode == 500) {
        // Parse even 500s — PHP now returns JSON for all errors
        final data = response.data is String
            ? jsonDecode(response.data)
            : response.data;
        return data is Map ? Map<String, dynamic>.from(data) : {'status': 'error', 'message': 'Unexpected response'};
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error uploading delivery POD: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Driver KYC Methods
  static Future<Map<String, dynamic>> getDriverKYCStatus({
    required String firebaseUid,
  }) async {
    try {
      final response = await _dio.post(
        '$baseUrl/vendor/get_driver_kyc_status.php',
        data: {'firebase_uid': firebaseUid},
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> submitDriverKYC({
    required String firebaseUid,
    required String driverName,
    required String driverMobile,
    required String driverEmail,
    required String aadharNumber,
    required String panNumber,
    required String licenseNumber,
    required String vehicleNumber,
    required String address,
    required String city,
    required String state,
    required String pincode,
  }) async {
    try {
      final dio = Dio(); // Clean instance to bypass any BaseOptions bugs
      final response = await dio.post(
        '$baseUrl/vendor/submit_driver_kyc.php',
        data: jsonEncode({
          'firebase_uid': firebaseUid,
          'driver_name': driverName,
          'driver_mobile': driverMobile,
          'driver_email': driverEmail,
          'aadhar_number': aadharNumber,
          'pan_number': panNumber,
          'license_number': licenseNumber,
          'vehicle_number': vehicleNumber,
          'address': address,
          'city': city,
          'state': state,
          'pincode': pincode,
        }),
        options: Options(
          headers: {
            'Content-Type': 'application/json',
          },
          validateStatus: (status) => status! < 500,
        ),
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  static Future<Map<String, dynamic>> uploadDriverKYCDocument({
    required String firebaseUid,
    required String documentType,
    required XFile imageFile,
  }) async {
    try {
      FormData formData = FormData.fromMap({
        'firebase_uid': firebaseUid,
        'document_type': documentType,
        'document': await MultipartFile.fromBytes(
          await imageFile.readAsBytes(),
          filename: '${firebaseUid}_${documentType}_${DateTime.now().millisecondsSinceEpoch}.jpg',
        ),
      });

      final dio = Dio(); // Clean instance for clean multipart boundary
      final response = await dio.post(
        '$baseUrl/vendor/upload_driver_kyc_documents.php',
        data: formData,
        options: Options(
          validateStatus: (status) => status! < 500,
        ),
      );

      if (response.statusCode == 200) {
        final data = response.data is String 
            ? jsonDecode(response.data) 
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

  // Get driver live location (for vendor tracking)
  static Future<Map<String, dynamic>> getDriverLocation({
    required int vehicleId,
  }) async {
    try {
      print('🔵 Getting driver location for vehicle $vehicleId...');
      final response = await _dio.post(
        '$baseUrl/vendor/get_driver_location.php',
        data: {'vehicle_id': vehicleId},
      );
      if (response.statusCode == 200) {
        final data = response.data is String
            ? jsonDecode(response.data)
            : response.data;
        return data;
      } else {
        return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
      }
    } catch (e) {
      print('🔴 Error getting driver location: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }

 
  static Future<Map<String, dynamic>> verifyOtp({
    required String trackingNumber,
    required String otpCode,
    required String otpType,
    required int vehicleId,
  }) async {
    try {
      print('🔵 Verifying OTP (tracking: $trackingNumber, type: $otpType)...');
      final response = await _dio.post(
        '$baseUrl/vendor/verify_otp.php',
        data: {
          'tracking_number': trackingNumber,
          'otp_code': otpCode,
          'otp_type': otpType,
          'vehicle_id': vehicleId,
        },
      );
      if (response.statusCode == 200) {
        final data = response.data is String ? jsonDecode(response.data) : response.data;
        return data;
      }
      return {'status': 'error', 'message': 'Server error: ${response.statusCode}'};
    } catch (e) {
      print('🔴 Error verifying OTP: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
  }
}
