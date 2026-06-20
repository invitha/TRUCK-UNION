<?php
/**
 * Mark Vendor Notification as Read
 * Updates notification read status
 */

// CORS headers MUST be first, before any other code
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$notification_id = isset($data['notification_id']) ? intval($data['notification_id']) : 0;
$mark_all = isset($data['mark_all']) ? (bool)$data['mark_all'] : false;

if (empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    exit();
}

if ($mark_all) {
    // Mark all notifications as read for this user
    $stmt = mysqli_prepare($con, "UPDATE notifications SET is_read = 1 WHERE firebase_uid = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'All notifications marked as read',
            'affected_rows' => $affected
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark notifications as read']);
    }
    
} else {
    // Mark specific notification as read
    if ($notification_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Notification ID is required']);
        exit();
    }
    
    $stmt = mysqli_prepare($con, "UPDATE notifications SET is_read = 1 WHERE id = ? AND firebase_uid = ?");
    mysqli_stmt_bind_param($stmt, 'is', $notification_id, $firebase_uid);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Notification not found or already read'
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark notification as read']);
    }
}

mysqli_close($con);
?>
