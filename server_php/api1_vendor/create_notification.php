<?php
/**
 * Helper function to create notifications
 * Can be included in other PHP files
 */

function createNotification($con, $firebase_uid, $type, $title, $message) {
    try {
        $stmt = mysqli_prepare($con, "
            INSERT INTO notifications (firebase_uid, type, title, message, is_read, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        if (!$stmt) {
            error_log("Failed to prepare notification statement: " . mysqli_error($con));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, 'ssss', $firebase_uid, $type, $title, $message);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($result) {
            error_log("✅ Notification created: $type for $firebase_uid");
            return true;
        } else {
            error_log("❌ Failed to create notification: " . mysqli_error($con));
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Exception creating notification: " . $e->getMessage());
        return false;
    }
}

// Standalone API endpoint
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit();
    }
    
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
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['firebase_uid']) || !isset($data['type']) || !isset($data['title']) || !isset($data['message'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit();
        }
        
        $result = createNotification(
            $con,
            $data['firebase_uid'],
            $data['type'],
            $data['title'],
            $data['message']
        );
        
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Notification created']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create notification']);
        }
        
        mysqli_close($con);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
}
