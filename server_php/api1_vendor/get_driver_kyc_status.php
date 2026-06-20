<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Database connection (Hardcoded)
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

// Get firebase_uid from POST or GET
$firebase_uid = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
} else {
    $firebase_uid = isset($_GET['firebase_uid']) ? trim($_GET['firebase_uid']) : '';
}

if (empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    mysqli_close($con);
    exit();
}

// Get KYC data
$query = "SELECT * FROM driver_kyc WHERE firebase_uid = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kyc_data = mysqli_fetch_assoc($result);

if ($kyc_data) {
    echo json_encode([
        'status' => 'success',
        'kyc_exists' => true,
        'kyc_data' => $kyc_data
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'kyc_exists' => false,
        'message' => 'No KYC data found'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
