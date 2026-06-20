#!/bin/bash
# Run these commands on your server as root

cd /home/royaldxd/crm.abra-logistic.com/api1/vendor/

# Backup the old file
cp get_notifications.php get_notifications.php.backup

# Replace with new version
cat > get_notifications.php << 'ENDOFFILE'
<?php
/**
 * Get Vendor Notifications
 * Returns all notifications for a vendor
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

if (!$data || !isset($data['firebase_uid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    exit();
}

$firebase_uid = trim($data['firebase_uid']);
$limit = isset($data['limit']) ? intval($data['limit']) : 50;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;

// Get notifications
$stmt = mysqli_prepare($con, "
    SELECT 
        id, firebase_uid, type, title, message, 
        is_read, created_at
    FROM notifications 
    WHERE firebase_uid = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

mysqli_stmt_bind_param($stmt, 'sii', $firebase_uid, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'message' => $row['message'],
        'is_read' => (bool)$row['is_read'],
        'created_at' => $row['created_at']
    ];
}

mysqli_stmt_close($stmt);

// Get unread count
$count_stmt = mysqli_prepare($con, "SELECT COUNT(*) as unread_count FROM notifications WHERE firebase_uid = ? AND is_read = 0");
mysqli_stmt_bind_param($count_stmt, 's', $firebase_uid);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_result);
$unread_count = $count_row['unread_count'];
mysqli_stmt_close($count_stmt);

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications,
    'unread_count' => $unread_count,
    'total' => count($notifications)
]);

mysqli_close($con);
ENDOFFILE

# Set correct permissions and owner
chmod 644 get_notifications.php
chown royaldxd:royaldxd get_notifications.php

echo "✅ File replaced successfully!"
echo ""
echo "Now test: https://crm.abra-logistic.com/api1/vendor/check_file_version.php"
