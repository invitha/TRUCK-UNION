<?php
/**
 * Get Vendor KYC Status
 * Returns the current KYC status and details for a vendor
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once('database.php');
    require_once('library.php');
    require_once('funciones.php');
    
    $con = conexion();
    if (!$con) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit();
    }
    mysqli_set_charset($con, 'utf8mb4');
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['firebase_uid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    exit();
}

$firebase_uid = trim($data['firebase_uid']);

// Get KYC details
$stmt = mysqli_prepare($con, "
    SELECT 
        id, firebase_uid, account_type, name, email, phone,
        aadhaar_number, pan_number,
        company_name, gst_number, address,
        bank_account_name, bank_account_number, ifsc_code,
        aadhaar_doc, pan_doc, photo_doc,
        gst_doc, address_doc, bank_account_photo,
        kyc_status, rejection_reason, verified_at,
        created_at, updated_at
    FROM vendor_kyc 
    WHERE firebase_uid = ?
");

mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kyc = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($kyc) {
    echo json_encode([
        'status' => 'success',
        'kyc_status' => $kyc['kyc_status'],
        'kyc_id' => $kyc['id'],
        'account_type' => $kyc['account_type'],
        'name' => $kyc['name'],
        'email' => $kyc['email'],
        'phone' => $kyc['phone'],
        'aadhaar_number' => $kyc['aadhaar_number'],
        'pan_number' => $kyc['pan_number'],
        'company_name' => $kyc['company_name'],
        'gst_number' => $kyc['gst_number'],
        'address' => $kyc['address'],
        'bank_account_name' => $kyc['bank_account_name'],
        'bank_account_number' => $kyc['bank_account_number'],
        'ifsc_code' => $kyc['ifsc_code'],
        'aadhaar_doc' => $kyc['aadhaar_doc'],
        'pan_doc' => $kyc['pan_doc'],
        'photo_doc' => $kyc['photo_doc'],
        'gst_doc' => $kyc['gst_doc'],
        'address_doc' => $kyc['address_doc'],
        'bank_account_photo' => $kyc['bank_account_photo'],
        'rejection_reason' => $kyc['rejection_reason'],
        'verified_at' => $kyc['verified_at'],
        'created_at' => $kyc['created_at'],
        'updated_at' => $kyc['updated_at']
    ]);
} else {
    // No KYC found - return not submitted status
    echo json_encode([
        'status' => 'success',
        'kyc_status' => 'not_submitted',
        'message' => 'No KYC found for this user'
    ]);
}

mysqli_close($con);
?>
