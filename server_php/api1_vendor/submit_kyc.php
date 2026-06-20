<?php
/**
 * Submit Vendor KYC
 * Receives KYC submission from Flutter app and stores in vendor_kyc table
 * Sends notification to vendor
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit();
    }
    
    $con->set_charset('utf8mb4');
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

// Required fields
$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$account_type = isset($data['account_type']) ? trim($data['account_type']) : 'individual';
$name = isset($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$aadhaar_number = isset($data['aadhaar_number']) ? trim($data['aadhaar_number']) : '';
$pan_number = isset($data['pan_number']) ? trim($data['pan_number']) : '';

// Bank account details (mandatory)
$bank_account_name = isset($data['bank_account_name']) ? trim($data['bank_account_name']) : '';
$bank_account_number = isset($data['bank_account_number']) ? trim($data['bank_account_number']) : '';
$ifsc_code = isset($data['ifsc_code']) ? trim($data['ifsc_code']) : '';

// Optional fields
$company_name = isset($data['company_name']) ? trim($data['company_name']) : null;
$gst_number = isset($data['gst_number']) ? trim($data['gst_number']) : null;
$address = isset($data['address']) ? trim($data['address']) : null;

// Document paths
$aadhaar_doc = isset($data['aadhaar_doc']) ? trim($data['aadhaar_doc']) : null;
$pan_doc = isset($data['pan_doc']) ? trim($data['pan_doc']) : null;
$photo_doc = isset($data['photo_doc']) ? trim($data['photo_doc']) : null;
$gst_doc = isset($data['gst_doc']) ? trim($data['gst_doc']) : null;
$address_doc = isset($data['address_doc']) ? trim($data['address_doc']) : null;
$bank_account_photo = isset($data['bank_account_photo']) ? trim($data['bank_account_photo']) : null;

// Validation
if (empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    exit();
}

if (empty($name) || empty($email) || empty($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Name, email, and phone are required']);
    exit();
}

if (empty($aadhaar_number) || empty($pan_number)) {
    echo json_encode(['status' => 'error', 'message' => 'Aadhaar and PAN numbers are required']);
    exit();
}

if (empty($bank_account_name) || empty($bank_account_number) || empty($ifsc_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Bank account details are required']);
    exit();
}

// Validate account type
if (!in_array($account_type, ['individual', 'business'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid account type']);
    exit();
}

// Check if KYC already exists for this user
$check_stmt = mysqli_prepare($con, "SELECT id, kyc_status FROM vendor_kyc WHERE firebase_uid = ?");
mysqli_stmt_bind_param($check_stmt, 's', $firebase_uid);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$existing = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if ($existing) {
    // Update existing KYC
    $kyc_id = $existing['id'];
    
    $update_stmt = mysqli_prepare($con, "
        UPDATE vendor_kyc SET
            account_type = ?,
            name = ?,
            email = ?,
            phone = ?,
            aadhaar_number = ?,
            pan_number = ?,
            company_name = ?,
            gst_number = ?,
            address = ?,
            bank_account_name = ?,
            bank_account_number = ?,
            ifsc_code = ?,
            aadhaar_doc = ?,
            pan_doc = ?,
            photo_doc = ?,
            gst_doc = ?,
            address_doc = ?,
            bank_account_photo = ?,
            kyc_status = 'submitted',
            rejection_reason = NULL,
            updated_at = NOW()
        WHERE firebase_uid = ?
    ");
    
    mysqli_stmt_bind_param($update_stmt, 'sssssssssssssssssss',
        $account_type, $name, $email, $phone,
        $aadhaar_number, $pan_number,
        $company_name, $gst_number, $address,
        $bank_account_name, $bank_account_number, $ifsc_code,
        $aadhaar_doc, $pan_doc, $photo_doc,
        $gst_doc, $address_doc, $bank_account_photo,
        $firebase_uid
    );
    
    if (mysqli_stmt_execute($update_stmt)) {
        mysqli_stmt_close($update_stmt);
        
        // Send notification
        $notif_stmt = mysqli_prepare($con, "INSERT INTO notifications (firebase_uid, type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($notif_stmt) {
            $type = 'kyc_resubmitted';
            $title = '📋 KYC Resubmitted';
            $message = 'Your KYC has been resubmitted for review. We will notify you once verified.';
            mysqli_stmt_bind_param($notif_stmt, 'ssss', $firebase_uid, $type, $title, $message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'KYC updated and resubmitted successfully',
            'kyc_id' => $kyc_id,
            'kyc_status' => 'submitted'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update KYC: ' . mysqli_error($con)]);
    }
    
} else {
    // Insert new KYC
    $insert_stmt = mysqli_prepare($con, "
        INSERT INTO vendor_kyc (
            firebase_uid, account_type, name, email, phone,
            aadhaar_number, pan_number,
            company_name, gst_number, address,
            bank_account_name, bank_account_number, ifsc_code,
            aadhaar_doc, pan_doc, photo_doc,
            gst_doc, address_doc, bank_account_photo,
            kyc_status, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            'submitted', NOW(), NOW()
        )
    ");
    
    mysqli_stmt_bind_param($insert_stmt, 'ssssssssssssssssss',
        $firebase_uid, $account_type, $name, $email, $phone,
        $aadhaar_number, $pan_number,
        $company_name, $gst_number, $address,
        $bank_account_name, $bank_account_number, $ifsc_code,
        $aadhaar_doc, $pan_doc, $photo_doc,
        $gst_doc, $address_doc, $bank_account_photo
    );
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $kyc_id = mysqli_insert_id($con);
        mysqli_stmt_close($insert_stmt);
        
        // Send notification
        $notif_stmt = mysqli_prepare($con, "INSERT INTO notifications (firebase_uid, type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($notif_stmt) {
            $type = 'kyc_submitted';
            $title = '✅ KYC Submitted';
            $message = 'Your KYC has been submitted for review. We will notify you once verified.';
            mysqli_stmt_bind_param($notif_stmt, 'ssss', $firebase_uid, $type, $title, $message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'KYC submitted successfully',
            'kyc_id' => $kyc_id,
            'kyc_status' => 'submitted'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit KYC: ' . mysqli_error($con)]);
    }
}

mysqli_close($con);
?>
