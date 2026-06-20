<?php
/**
 * Vendor KYC Document Upload with ACTUAL File Upload
 * Based on abra_app pattern
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
    }
    
    $con->set_charset('utf8mb4');
    
    // Include notification helper
    require_once('create_notification.php');
    
    // Get form data
    $firebase_uid = $_POST['firebase_uid'] ?? '';
    $account_type = $_POST['account_type'] ?? 'individual';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $aadhaar_number = $_POST['aadhaar_number'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';
    $company_name = $_POST['company_name'] ?? null;
    $gst_number = $_POST['gst_number'] ?? null;
    $address = $_POST['address'] ?? null;
    $bank_account_name = $_POST['bank_account_name'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    
    if (empty($firebase_uid)) {
        die(json_encode(['status' => 'error', 'message' => 'Firebase UID required']));
    }
    
    // Create uploads directory - ABSOLUTE PATH
    $upload_base_dir = '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents';
    if (!file_exists($upload_base_dir)) {
        mkdir($upload_base_dir, 0755, true);
    }
    
    // Create user-specific directory
    $user_dir = $upload_base_dir . '/' . $firebase_uid;
    if (!file_exists($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    // Handle file uploads
    $uploaded_documents = [];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $document_types = [
        'aadhaar' => 'aadhaar_doc',
        'pan' => 'pan_doc',
        'photo' => 'photo_doc',
        'gst' => 'gst_doc',
        'address_proof' => 'address_doc',
        'bank_account_photo' => 'bank_account_photo'
    ];
    
    // Check if KYC exists to get old documents
    $stmt = $con->prepare("SELECT aadhaar_doc, pan_doc, photo_doc, gst_doc, address_doc, bank_account_photo FROM vendor_kyc WHERE firebase_uid = ?");
    $stmt->bind_param('s', $firebase_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    // Process file uploads
    foreach ($document_types as $upload_name => $db_field) {
        if (isset($_FILES[$upload_name]) && $_FILES[$upload_name]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$upload_name];
            
            // Validate file size
            if ($file['size'] > $max_file_size) {
                die(json_encode(['status' => 'error', 'message' => ucfirst($upload_name) . ' file is too large (max 5MB)']));
            }
            
            // Validate file extension
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                die(json_encode(['status' => 'error', 'message' => ucfirst($upload_name) . ' file type not allowed']));
            }
            
            // Generate unique filename
            $new_filename = $upload_name . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $destination = $user_dir . '/' . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $uploaded_documents[$db_field] = $new_filename;
                error_log("✅ Uploaded: $new_filename to $destination");
            } else {
                error_log("❌ Failed to upload: $upload_name");
                die(json_encode(['status' => 'error', 'message' => 'Failed to upload ' . $upload_name]));
            }
        } else {
            // Keep existing document if no new upload
            if ($existing && !empty($existing[$db_field])) {
                $uploaded_documents[$db_field] = $existing[$db_field];
            }
        }
    }
    
    // Prepare document values
    $aadhaar_doc = $uploaded_documents['aadhaar_doc'] ?? null;
    $pan_doc = $uploaded_documents['pan_doc'] ?? null;
    $photo_doc = $uploaded_documents['photo_doc'] ?? null;
    $gst_doc = $uploaded_documents['gst_doc'] ?? null;
    $address_doc = $uploaded_documents['address_doc'] ?? null;
    $bank_account_photo = $uploaded_documents['bank_account_photo'] ?? null;
    
    if ($existing) {
        // Update existing KYC
        $stmt = $con->prepare("
            UPDATE vendor_kyc 
            SET account_type=?, name=?, email=?, phone=?, 
                aadhaar_number=?, pan_number=?, company_name=?, gst_number=?, address=?, 
                bank_account_name=?, bank_account_number=?, ifsc_code=?, 
                aadhaar_doc=?, pan_doc=?, photo_doc=?, gst_doc=?, address_doc=?, bank_account_photo=?, 
                kyc_status='submitted', updated_at=NOW() 
            WHERE firebase_uid=?
        ");
        $stmt->bind_param(
            'sssssssssssssssssss',
            $account_type, $name, $email, $phone,
            $aadhaar_number, $pan_number, $company_name, $gst_number, $address,
            $bank_account_name, $bank_account_number, $ifsc_code,
            $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo,
            $firebase_uid
        );
    } else {
        // Insert new KYC
        $stmt = $con->prepare("
            INSERT INTO vendor_kyc 
            (firebase_uid, account_type, name, email, phone, 
             aadhaar_number, pan_number, company_name, gst_number, address, 
             bank_account_name, bank_account_number, ifsc_code, 
             aadhaar_doc, pan_doc, photo_doc, gst_doc, address_doc, bank_account_photo, 
             kyc_status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW(), NOW())
        ");
        $stmt->bind_param(
            'sssssssssssssssssss',
            $firebase_uid, $account_type, $name, $email, $phone,
            $aadhaar_number, $pan_number, $company_name, $gst_number, $address,
            $bank_account_name, $bank_account_number, $ifsc_code,
            $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo
        );
    }
    
    if ($stmt->execute()) {
        // Create notification for KYC submission
        createNotification(
            $con,
            $firebase_uid,
            'kyc_submitted',
            '📋 KYC Submitted Successfully',
            'Your KYC documents have been submitted and are under review. Verification usually takes 24-48 hours.'
        );
        
        echo json_encode([
            'status' => 'success',
            'message' => 'KYC submitted successfully',
            'uploaded_files' => $uploaded_documents
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $stmt->error]);
    }
    
    $stmt->close();
    $con->close();
    
} catch (Exception $e) {
    error_log("❌ Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
