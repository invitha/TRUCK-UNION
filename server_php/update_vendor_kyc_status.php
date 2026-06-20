<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firebase_uid = $input['firebase_uid'] ?? '';
    $kyc_status = $input['kyc_status'] ?? '';
    $admin_notes = $input['admin_notes'] ?? '';
    
    if (empty($firebase_uid) || empty($kyc_status)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }
    
    // Validate status
    $valid_statuses = ['submitted', 'verified', 'rejected'];
    if (!in_array($kyc_status, $valid_statuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
        exit();
    }
    
    // Update KYC status
    $stmt = $pdo->prepare("
        UPDATE vendor_kyc 
        SET kyc_status = ?, 
            admin_notes = ?,
            updated_at = NOW()
        WHERE firebase_uid = ?
    ");
    
    $stmt->execute([$kyc_status, $admin_notes, $firebase_uid]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'KYC status updated successfully',
            'new_status' => $kyc_status
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No KYC found or status unchanged'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
