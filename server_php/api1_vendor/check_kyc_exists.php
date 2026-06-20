<?php
/**
 * Check if Vendor KYC Details Already Exist
 * Validates Aadhaar, PAN, and Bank Account uniqueness
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
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

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['firebase_uid'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Firebase UID is required'
    ]);
    exit;
}

$firebase_uid = $input['firebase_uid'];
$aadhaar_number = $input['aadhaar_number'] ?? null;
$pan_number = $input['pan_number'] ?? null;
$bank_account_number = $input['bank_account_number'] ?? null;

$exists = false;
$aadhaar_exists = false;
$pan_exists = false;
$bank_account_exists = false;

// Check Aadhaar number (exclude current user)
if ($aadhaar_number) {
    $stmt = mysqli_prepare($con, "SELECT firebase_uid FROM vendor_kyc WHERE aadhaar_number = ? AND firebase_uid != ?");
    mysqli_stmt_bind_param($stmt, "ss", $aadhaar_number, $firebase_uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $aadhaar_exists = true;
        $exists = true;
    }
    mysqli_stmt_close($stmt);
}

// Check PAN number (exclude current user)
if ($pan_number) {
    $stmt = mysqli_prepare($con, "SELECT firebase_uid FROM vendor_kyc WHERE pan_number = ? AND firebase_uid != ?");
    mysqli_stmt_bind_param($stmt, "ss", $pan_number, $firebase_uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $pan_exists = true;
        $exists = true;
    }
    mysqli_stmt_close($stmt);
}

// Check Bank Account number (exclude current user)
if ($bank_account_number) {
    $stmt = mysqli_prepare($con, "SELECT firebase_uid FROM vendor_kyc WHERE bank_account_number = ? AND firebase_uid != ?");
    mysqli_stmt_bind_param($stmt, "ss", $bank_account_number, $firebase_uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $bank_account_exists = true;
        $exists = true;
    }
    mysqli_stmt_close($stmt);
}

if ($exists) {
    echo json_encode([
        'status' => 'exists',
        'message' => 'KYC details already registered',
        'aadhaar_exists' => $aadhaar_exists,
        'pan_exists' => $pan_exists,
        'bank_account_exists' => $bank_account_exists
    ]);
} else {
    echo json_encode([
        'status' => 'available',
        'message' => 'KYC details are available'
    ]);
}

mysqli_close($con);
?>
