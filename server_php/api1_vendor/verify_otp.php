<?php
/**
 * Verify OTP — Driver submits pickup or delivery OTP to confirm with customer
 * POST { tracking_number, otp_type: 'pickup'|'delivery', otp_code, vehicle_id }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
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
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
    exit();
}

// Auto-add OTP columns if they don't exist yet
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS pickup_otp VARCHAR(6) DEFAULT NULL");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS delivery_otp VARCHAR(6) DEFAULT NULL");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS pickup_otp_verified TINYINT(1) DEFAULT 0");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS delivery_otp_verified TINYINT(1) DEFAULT 0");

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    mysqli_close($con);
    exit();
}

$tracking_number = isset($data['tracking_number']) ? trim($data['tracking_number']) : '';
$otp_type        = isset($data['otp_type'])        ? trim($data['otp_type'])        : ''; // 'pickup' | 'delivery'
$otp_code        = isset($data['otp_code'])        ? trim($data['otp_code'])        : '';
$vehicle_id      = isset($data['vehicle_id'])      ? intval($data['vehicle_id'])     : 0;

if (empty($tracking_number) || empty($otp_type) || empty($otp_code) || empty($vehicle_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    mysqli_close($con);
    exit();
}

if (!in_array($otp_type, ['pickup', 'delivery'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP type']);
    mysqli_close($con);
    exit();
}

// Fetch the order — verify vehicle ownership via fleet_assignments
$stmt = $con->prepare("
    SELECT c.cid, c.pickup_otp, c.delivery_otp, c.pickup_otp_verified, c.delivery_otp_verified, c.status
    FROM courier c
    JOIN fleet_assignments f ON TRIM(c.assigned_vehicle) = TRIM(f.al_number)
    WHERE c.tracking = ? AND f.vehicle_id = ? AND f.status IN ('active','pending')
    LIMIT 1
");
$stmt->bind_param('si', $tracking_number, $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$order  = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found or not assigned to this vehicle']);
    mysqli_close($con);
    exit();
}

// Auto-generate OTPs if they're missing (lazy generation for existing orders)
if (empty($order['pickup_otp']) || empty($order['delivery_otp'])) {
    $new_pickup_otp   = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $new_delivery_otp = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $upd = $con->prepare("UPDATE courier SET pickup_otp = ?, delivery_otp = ? WHERE cid = ?");
    $upd->bind_param('ssi', $new_pickup_otp, $new_delivery_otp, $order['cid']);
    $upd->execute();
    $upd->close();
    $order['pickup_otp']   = $new_pickup_otp;
    $order['delivery_otp'] = $new_delivery_otp;
}

// Get the stored OTP for the requested type
$stored_otp = ($otp_type === 'pickup') ? $order['pickup_otp'] : $order['delivery_otp'];
$already_verified = ($otp_type === 'pickup')
    ? $order['pickup_otp_verified']
    : $order['delivery_otp_verified'];

// Already verified?
if ($already_verified) {
    echo json_encode(['status' => 'already_verified', 'message' => ucfirst($otp_type) . ' OTP already verified']);
    mysqli_close($con);
    exit();
}

// Validate OTP
if ($otp_code !== $stored_otp) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please check and try again.']);
    mysqli_close($con);
    exit();
}

// OTP correct — mark as verified + update courier status
if ($otp_type === 'pickup') {
    $new_status   = 'Picked Up';
    $verified_col = 'pickup_otp_verified';
} else {
    $new_status   = 'Delivered';
    $verified_col = 'delivery_otp_verified';
}

$upd = $con->prepare("UPDATE courier SET $verified_col = 1, status = ? WHERE cid = ?");
$upd->bind_param('si', $new_status, $order['cid']);
$upd->execute();
$upd->close();

// Also add timeline entry in courier_track
$cons_no = $tracking_number;
$letra   = $tracking_number;
$note    = "OTP verified by driver for $otp_type";
$tk_stmt = $con->prepare("
    INSERT INTO courier_track (cid, cons_no, letra, pick_time, status, detailed_status, comments, bk_time)
    VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW())
");
$tk_stmt->bind_param('isssss', $order['cid'], $cons_no, $letra, $new_status, $note, $note);
$tk_stmt->execute();
$tk_stmt->close();

echo json_encode([
    'status'     => 'success',
    'message'    => ucfirst($otp_type) . ' confirmed successfully!',
    'new_status' => $new_status
]);

mysqli_close($con);
