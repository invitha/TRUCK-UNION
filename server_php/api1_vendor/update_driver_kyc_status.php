<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../db_config.php';

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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    mysqli_close($con);
    exit();
}

$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$new_status = isset($data['status']) ? trim($data['status']) : '';
$rejection_reason = isset($data['rejection_reason']) ? trim($data['rejection_reason']) : null;
$admin_notes = isset($data['admin_notes']) ? trim($data['admin_notes']) : null;

if (empty($firebase_uid) || empty($new_status)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID and status are required']);
    mysqli_close($con);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'submitted', 'under_review', 'verified', 'rejected', 'revoked'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    mysqli_close($con);
    exit();
}

// Build update query based on status
$timestamp_field = '';
if ($new_status === 'verified') {
    $timestamp_field = ', verified_at = NOW()';
} elseif ($new_status === 'rejected') {
    $timestamp_field = ', rejected_at = NOW()';
}

$query = "UPDATE driver_kyc SET 
          kyc_status = ?,
          rejection_reason = ?,
          admin_notes = ?,
          updated_at = NOW()
          $timestamp_field
          WHERE firebase_uid = ?";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'ssss', $new_status, $rejection_reason, $admin_notes, $firebase_uid);

if (mysqli_stmt_execute($stmt)) {
    // Create notification for driver
    $notification_title = '';
    $notification_message = '';
    
    if ($new_status === 'verified') {
        $notification_title = 'KYC Verified ✓';
        $notification_message = 'Congratulations! Your KYC has been verified. You can now start accepting orders.';
    } elseif ($new_status === 'rejected') {
        $notification_title = 'KYC Rejected';
        $notification_message = 'Your KYC has been rejected. ' . ($rejection_reason ? 'Reason: ' . $rejection_reason : 'Please resubmit with correct documents.');
    } elseif ($new_status === 'under_review') {
        $notification_title = 'KYC Under Review';
        $notification_message = 'Your KYC documents are being reviewed. You will be notified once verified.';
    } elseif ($new_status === 'revoked') {
        $notification_title = 'KYC Revoked';
        $notification_message = 'Your KYC verification has been revoked. Please contact support.';
    }
    
    if (!empty($notification_title)) {
        $notif_query = "INSERT INTO notifications (firebase_uid, title, message, type, created_at) 
                        VALUES (?, ?, ?, 'kyc_update', NOW())";
        $notif_stmt = mysqli_prepare($con, $notif_query);
        mysqli_stmt_bind_param($notif_stmt, 'sss', $firebase_uid, $notification_title, $notification_message);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Driver KYC status updated successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
