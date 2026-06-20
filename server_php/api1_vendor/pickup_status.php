<?php
/**
 * Pickup Status with Photo Upload
 * VERSION: 2.1 — PICKUP ONLY, robust error handling
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Catch ALL PHP errors and return as JSON instead of 500
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'PHP error: ' . $errstr,
        'errno'   => $errno,
        'line'    => $errline,
    ]);
    exit();
});

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// GET → version check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'file'    => 'pickup_status.php',
        'version' => '2.1',
        'type'    => 'pickup',
        'status'  => 'ok',
        'note'    => 'POST multipart/form-data with pickupPhoto to use this endpoint',
    ]);
    exit();
}

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $con->connect_error]);
        exit();
    }
    $con->set_charset('utf8mb4');
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}

// ── Read POST fields ─────────────────────────────────────────────────────
$tracking      = isset($_POST['tracking'])       ? trim($_POST['tracking'])       : '';
$al_number     = isset($_POST['al_number'])      ? trim($_POST['al_number'])      : '';
$vehicle_id    = isset($_POST['vehicle_id'])     ? intval($_POST['vehicle_id'])   : 0;
$pickup_driver = isset($_POST['pickupDriverId']) ? trim($_POST['pickupDriverId']) : '';
$status        = isset($_POST['status'])         ? trim($_POST['status'])         : 'Picked Up';
$latitude      = isset($_POST['latitude'])       ? trim($_POST['latitude'])       : null;
$longitude     = isset($_POST['longitude'])      ? trim($_POST['longitude'])      : null;
$reason        = isset($_POST['reason'])         ? trim($_POST['reason'])         : null;

if (empty($tracking)) $tracking = $al_number;
if (empty($al_number)) $al_number = $tracking;

if (empty($tracking)) {
    echo json_encode([
        'status'      => 'error',
        'message'     => 'tracking / al_number is required',
        'received_post_keys' => array_keys($_POST),
        'received_files_keys' => array_keys($_FILES),
    ]);
    mysqli_close($con);
    exit();
}

// ── Validate file upload ─────────────────────────────────────────────────
if (!isset($_FILES['pickupPhoto'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'pickupPhoto file not received',
        'files_received' => array_keys($_FILES),
    ]);
    mysqli_close($con);
    exit();
}

if ($_FILES['pickupPhoto']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        1 => 'File exceeds upload_max_filesize',
        2 => 'File exceeds MAX_FILE_SIZE',
        3 => 'File only partially uploaded',
        4 => 'No file uploaded',
        6 => 'Missing temp folder',
        7 => 'Failed to write to disk',
        8 => 'Upload stopped by extension',
    ];
    $err_code = $_FILES['pickupPhoto']['error'];
    echo json_encode([
        'status'       => 'error',
        'message'      => 'File upload error: ' . ($upload_errors[$err_code] ?? 'Unknown error ' . $err_code),
        'upload_error_code' => $err_code,
    ]);
    mysqli_close($con);
    exit();
}

if ($_FILES['pickupPhoto']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File exceeds 10MB limit']);
    mysqli_close($con);
    exit();
}

// Safe mime check — mime_content_type can fail on some servers
$file_type = '';
if (function_exists('mime_content_type')) {
    $file_type = @mime_content_type($_FILES['pickupPhoto']['tmp_name']);
}
if (empty($file_type)) {
    // Fallback: use file extension from original name
    $ext_lower = strtolower(pathinfo($_FILES['pickupPhoto']['name'], PATHINFO_EXTENSION));
    $ext_to_mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $file_type = $ext_to_mime[$ext_lower] ?? 'image/jpeg';
}

$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type: ' . $file_type]);
    mysqli_close($con);
    exit();
}

// ── Save file — uploads/ is at webroot, two levels up from this file ────
// This file: /api1/vendor/pickup_status.php
// Webroot:   /  (one level above api1/)
// uploads/:  /uploads/pickup-photos/
$uploads_dir = dirname(dirname(__DIR__)) . '/uploads/pickup-photos/';

if (!file_exists($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}

if (!is_writable($uploads_dir)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Uploads directory is not writable',
        'path'    => $uploads_dir,
    ]);
    mysqli_close($con);
    exit();
}

$file_ext = strtolower(pathinfo($_FILES['pickupPhoto']['name'], PATHINFO_EXTENSION));
if (empty($file_ext)) $file_ext = 'jpg';

// Sanitize tracking for filename (remove slashes, spaces)
$safe_tracking = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tracking);
$filename      = $safe_tracking . '_' . date('YmdHis') . '.' . $file_ext;
$file_path     = $uploads_dir . $filename;

// Relative path for DB storage
$relative_path = 'uploads/pickup-photos/' . $filename;

// Build full public URL — use server's own domain so this works on any host
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host_name = $_SERVER['HTTP_HOST'] ?? 'crm.abra-logistic.com';
$full_url  = $scheme . '://' . $host_name . '/' . $relative_path;

if (!move_uploaded_file($_FILES['pickupPhoto']['tmp_name'], $file_path)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to save photo',
        'uploads_dir' => $uploads_dir,
        'is_writable' => is_writable($uploads_dir),
    ]);
    mysqli_close($con);
    exit();
}

// ── Sanitize & update DB ──────────────────────────────────────────────────
$s_tracking  = $con->real_escape_string($tracking);
$s_status    = $con->real_escape_string($status);
$s_driver_id = $con->real_escape_string($pickup_driver);
$s_reason    = !empty($reason) ? $con->real_escape_string($reason) : null;
$s_relative  = $con->real_escape_string($relative_path);
$s_full_url  = $con->real_escape_string($full_url);
$detail      = !empty($s_reason) ? $s_reason : $s_status;

$sql_update = "UPDATE royaldxd_abra_crm.courier
               SET status = '$s_status', detailed_status = '$detail',
                   update_remarks = '$detail', last_updated = current_timestamp()
               WHERE (tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking')
               AND status IN ('Pickup Assigned', 'Pickup Accepted', 'Reaching at Loading Point', 'Failed Pickup')";
$con->query($sql_update);
$affected = $con->affected_rows;

if ($affected <= 0) {
    $sql_update2 = "UPDATE royaldxd_abra_crm.courier
                    SET status = '$s_status', detailed_status = '$detail',
                        update_remarks = '$detail', last_updated = current_timestamp()
                    WHERE (tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking')";
    $con->query($sql_update2);
    $affected = $con->affected_rows;
}

// ── Also write POD columns directly on courier (same as abra_logistics) ──
// This is what pickup-pod.php dashboard reads
$lat_sql = (!empty($latitude)  && is_numeric($latitude))  ? floatval($latitude)  : 'NULL';
$lon_sql = (!empty($longitude) && is_numeric($longitude)) ? floatval($longitude) : 'NULL';
$con->query("UPDATE royaldxd_abra_crm.courier
             SET pickup_pod_image     = '$s_full_url',
                 pickup_pod_timestamp = NOW(),
                 pickup_latitude      = $lat_sql,
                 pickup_longitude     = $lon_sql,
                 pickup_driver_id     = '$s_driver_id'
             WHERE (tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking')");

// ── INSERT milestone into tsp_milestones — always, even if driver_id empty ──
$driver_val = !empty($s_driver_id) ? "'$s_driver_id'" : "''";
$reason_sql = !empty($s_reason)    ? "'$s_reason'"    : 'NULL';

$sql_milestone = "INSERT INTO tsp_milestones
                  (delivery_id, tracking, trStatus, trLatitude, trLongitude, trReason, trPODimage)
                  VALUES ($driver_val, '$s_tracking', '$s_status', $lat_sql, $lon_sql, $reason_sql, '$s_full_url')";
$con->query($sql_milestone);

mysqli_close($con);

echo json_encode([
    'status'    => 'success',
    'message'   => ($status === 'Picked Up') ? 'Pickup confirmed successfully' : 'Failed pickup submitted',
    'photo_url' => $full_url,
    'photo_relative' => $relative_path,
    'pod_type'  => 'pickup',
    'version'   => '2.1',
]);
