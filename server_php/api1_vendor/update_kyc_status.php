<?php
/**
 * Update Vendor KYC Status (Admin Only)
 * Approves or rejects KYC and sends notification
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    
    // Include notification helper
    require_once('create_notification.php');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['firebase_uid']) || !isset($data['kyc_status'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }
    
    $firebase_uid = trim($data['firebase_uid']);
    $kyc_status = trim($data['kyc_status']);
    $rejection_reason = isset($data['rejection_reason']) ? trim($data['rejection_reason']) : null;
    
    // Validate status
    if (!in_array($kyc_status, ['verified', 'rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid KYC status']);
        exit();
    }
    
    // Update KYC status
    if ($kyc_status === 'verified') {
        $stmt = mysqli_prepare($con, "
            UPDATE vendor_kyc 
            SET kyc_status = 'verified', 
                verified_at = NOW(), 
                rejection_reason = NULL,
                updated_at = NOW() 
            WHERE firebase_uid = ?
        ");
        mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
    } else {
        // Reject
        $stmt = mysqli_prepare($con, "
            UPDATE vendor_kyc 
            SET kyc_status = 'rejected', 
                rejection_reason = ?,
                verified_at = NULL,
                updated_at = NOW() 
            WHERE firebase_uid = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $rejection_reason, $firebase_uid);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update KYC status']);
        mysqli_stmt_close($stmt);
        exit();
    }
    
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected === 0) {
        echo json_encode(['status' => 'error', 'message' => 'KYC record not found']);
        exit();
    }
    
    // Create notification based on status
    if ($kyc_status === 'verified') {
        createNotification(
            $con,
            $firebase_uid,
            'kyc_approved',
            '✅ KYC Verified Successfully!',
            'Congratulations! Your KYC has been verified. You can now add vehicles and start accepting orders.'
        );
    } else {
        // Rejected
        $reason_text = $rejection_reason ? " Reason: $rejection_reason" : '';
        createNotification(
            $con,
            $firebase_uid,
            'kyc_rejected',
            '❌ KYC Verification Failed',
            "Your KYC verification was rejected.$reason_text Please re-submit with correct documents."
        );
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "KYC $kyc_status successfully",
        'kyc_status' => $kyc_status
    ]);
    
    mysqli_close($con);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
