<?php
/**
 * Save FCM Token for Vendor or Driver
 * Called by Flutter app after login to register the device token for push notifications.
 *
 * POST body (JSON):
 *   firebase_uid  — vendor's Firebase UID  OR  "driver_<vehicle_id>" for drivers
 *   fcm_token     — device FCM token from FirebaseMessaging.instance.getToken()
 *   platform      — "android" | "ios" | "web"  (optional)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

error_reporting(E_ALL);
ini_set('display_errors', 0);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit();
}
$con->set_charset('utf8mb4');

// Auto-create table if it doesn't exist
$con->query("CREATE TABLE IF NOT EXISTS vendor_fcm_tokens (
    id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(255) NOT NULL,
    fcm_token    TEXT NOT NULL,
    platform     VARCHAR(20) DEFAULT 'android',
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_uid (firebase_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$firebase_uid = trim($body['firebase_uid'] ?? '');
$fcm_token    = trim($body['fcm_token']    ?? '');
$platform     = trim($body['platform']     ?? 'android');

if (empty($firebase_uid) || empty($fcm_token)) {
    echo json_encode(['status' => 'error', 'message' => 'firebase_uid and fcm_token are required']);
    exit();
}

// UPSERT — insert or update on duplicate UID
$stmt = $con->prepare("
    INSERT INTO vendor_fcm_tokens (firebase_uid, fcm_token, platform, updated_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE fcm_token = VALUES(fcm_token), platform = VALUES(platform), updated_at = NOW()
");
$stmt->bind_param('sss', $firebase_uid, $fcm_token, $platform);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'FCM token saved']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$con->close();
?>
