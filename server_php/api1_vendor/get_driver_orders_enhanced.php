<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
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

// Ensure OTP columns exist (safe to run every time)
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS pickup_otp VARCHAR(10) DEFAULT NULL");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS delivery_otp VARCHAR(10) DEFAULT NULL");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS pickup_otp_verified TINYINT(1) DEFAULT 0");
$con->query("ALTER TABLE courier ADD COLUMN IF NOT EXISTS delivery_otp_verified TINYINT(1) DEFAULT 0");

$vehicle_id   = isset($_GET['vehicle_id'])   ? intval($_GET['vehicle_id'])        : 0;
$order_type   = isset($_GET['order_type'])   ? trim($_GET['order_type'])           : 'active'; // 'active' | 'completed'

if (empty($vehicle_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID is required']);
    mysqli_close($con);
    exit();
}

// Active orders: not yet delivered
// Completed orders: Delivered or Cancelled
if ($order_type === 'completed') {
    $status_clause        = "AND c.status IN ('Delivered', 'Cancelled')";
    $fleet_status_clause  = "AND f.status IN ('active', 'pending', 'completed', 'cancelled')";
    $order_dir            = "DESC"; // newest completed first
} else {
    $status_clause        = "AND c.status NOT IN ('Delivered', 'Cancelled', 'Pickup Declined')";
    $fleet_status_clause  = "AND f.status IN ('active', 'pending')";
    $order_dir            = "ASC";
}

$query = "SELECT
    c.cid as id,
    c.tracking as tracking_number,
    c.assigned_vehicle as al_number,
    c.reference_no,
    c.ship_name as customer_name,
    c.ship_name as sender_name,
    c.phone as sender_mobile,
    c.s_add as sender_address,
    c.rev_name as receiver_name,
    c.r_phone as receiver_mobile,
    c.r_add as receiver_address,
    c.ciudad as origin_pincode,
    c.city1 as dest_pincode,
    '' as route,
    c.shipping_subtotal as shipping_amount,
    c.paymode as payment_mode,
    c.shipping_method,
    c.type as original_type,
    '' as vehicle_type,
    c.pesoreal as actual_weight,
    c.status,
    c.comments as driver_notes,
    c.book_date,
    c.book_date as assigned_at,
    NULL as picked_up_at,
    NULL as delivered_at,
    NULL as current_location_lat,
    NULL as current_location_lng,
    NULL as location_updated_at,
    c.book_date as created_at,
    c.book_date as updated_at,
    IFNULL(c.pickup_otp_verified, 0) as pickup_otp_verified,
    IFNULL(c.delivery_otp_verified, 0) as delivery_otp_verified
FROM courier c
JOIN fleet_assignments f ON TRIM(c.assigned_vehicle) = TRIM(f.al_number)
WHERE f.vehicle_id = ?
$fleet_status_clause
$status_clause
GROUP BY c.cid
ORDER BY c.book_date $order_dir";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
$summary = [
    'total' => 0,
    'part_load' => 0,
    'ftl' => 0,
    'express' => 0,
    'pending_pickup' => 0,
    'in_transit' => 0,
];

while ($row = mysqli_fetch_assoc($result)) {
    $raw_method = strtoupper($row['shipping_method'] ?? '');
    if ($raw_method === 'FULL LOAD') {
        $row['load_category'] = 'ftl';
    } elseif ($raw_method === 'EXPRESS') {
        $row['load_category'] = 'express';
    } else {
        $row['load_category'] = 'part_load';
    }

    $orders[] = $row;
    $summary['total']++;
    
    // Count by load category
    $category = $row['load_category'];
    if (isset($summary[$category])) {
        $summary[$category]++;
    }
    
    // Count by status
    $status = strtolower($row['status'] ?? '');
    if (in_array($status, ['awb created', 'assigned', 'pickup assigned'])) {
        $summary['pending_pickup']++;
    } elseif (in_array($status, ['picked up', 'picked_up', 'in transit', 'in_transit'])) {
        $summary['in_transit']++;
    }
}

mysqli_stmt_close($stmt);
mysqli_close($con);

echo json_encode([
    'status' => 'success',
    'orders' => $orders,
    'summary' => $summary,
    'message' => count($orders) > 0 ? 'Orders retrieved successfully' : 'No active assignments found - waiting for vendor to accept assignments'
]);
?>
