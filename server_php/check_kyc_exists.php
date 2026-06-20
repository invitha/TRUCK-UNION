<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['firebase_uid'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Firebase UID is required'
        ]);
        exit;
    }
    
    $firebase_uid = $input['firebase_uid'];
    $aadhaar_number = isset($input['aadhaar_number']) ? $input['aadhaar_number'] : null;
    $pan_number = isset($input['pan_number']) ? $input['pan_number'] : null;
    $bank_account_number = isset($input['bank_account_number']) ? $input['bank_account_number'] : null;
    
    $exists = false;
    $aadhaar_exists = false;
    $pan_exists = false;
    $bank_account_exists = false;
    
    // Check Aadhaar number (exclude current user)
    if ($aadhaar_number) {
        $stmt = $conn->prepare("SELECT firebase_uid FROM vendor_kyc WHERE aadhaar_number = ? AND firebase_uid != ?");
        $stmt->bind_param("ss", $aadhaar_number, $firebase_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $aadhaar_exists = true;
            $exists = true;
        }
        $stmt->close();
    }
    
    // Check PAN number (exclude current user)
    if ($pan_number) {
        $stmt = $conn->prepare("SELECT firebase_uid FROM vendor_kyc WHERE pan_number = ? AND firebase_uid != ?");
        $stmt->bind_param("ss", $pan_number, $firebase_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $pan_exists = true;
            $exists = true;
        }
        $stmt->close();
    }
    
    // Check Bank Account number (exclude current user)
    if ($bank_account_number) {
        $stmt = $conn->prepare("SELECT firebase_uid FROM vendor_kyc WHERE bank_account_number = ? AND firebase_uid != ?");
        $stmt->bind_param("ss", $bank_account_number, $firebase_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bank_account_exists = true;
            $exists = true;
        }
        $stmt->close();
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
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
