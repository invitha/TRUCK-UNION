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
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        throw new Exception('Connection failed: ' . $con->connect_error);
    }
    
    $con->set_charset('utf8mb4');
    
    // Get test data
    $data = json_decode(file_get_contents('php://input'), true);
    
    $aadhaar = isset($data['aadhaar_number']) ? $data['aadhaar_number'] : '123456789012';
    $pan = isset($data['pan_number']) ? $data['pan_number'] : 'ABCDE1234F';
    $bank = isset($data['bank_account_number']) ? $data['bank_account_number'] : '1234567890';
    $firebase_uid = isset($data['firebase_uid']) ? $data['firebase_uid'] : 'test_uid';
    
    // Check Aadhaar
    $stmt = $con->prepare("SELECT firebase_uid FROM vendor_kyc WHERE aadhaar_number = ? AND firebase_uid != ?");
    $stmt->bind_param('ss', $aadhaar, $firebase_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $aadhaar_exists = $result->num_rows > 0;
    $stmt->close();
    
    // Check PAN
    $stmt = $con->prepare("SELECT firebase_uid FROM vendor_kyc WHERE pan_number = ? AND firebase_uid != ?");
    $stmt->bind_param('ss', $pan, $firebase_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $pan_exists = $result->num_rows > 0;
    $stmt->close();
    
    // Check Bank Account
    $stmt = $con->prepare("SELECT firebase_uid FROM vendor_kyc WHERE bank_account_number = ? AND firebase_uid != ?");
    $stmt->bind_param('ss', $bank, $firebase_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $bank_exists = $result->num_rows > 0;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'aadhaar_exists' => $aadhaar_exists,
        'pan_exists' => $pan_exists,
        'bank_account_exists' => $bank_exists,
        'tested_values' => [
            'aadhaar' => $aadhaar,
            'pan' => $pan,
            'bank' => $bank,
            'firebase_uid' => $firebase_uid
        ]
    ]);
    
    $con->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
