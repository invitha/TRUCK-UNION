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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    mysqli_close($con);
    exit();
}

$order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
$vehicle_id = isset($data['vehicle_id']) ? intval($data['vehicle_id']) : 0;
$new_status = isset($data['status']) ? trim($data['status']) : '';

if (empty($order_id) || empty($vehicle_id) || empty($new_status)) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID, Vehicle ID, and status are required']);
    mysqli_close($con);
    exit();
}

// Verify order belongs to this vehicle
$check_stmt = mysqli_prepare($con, "SELECT id FROM customer_orders WHERE id = ? AND vehicle_id = ?");
mysqli_stmt_bind_param($check_stmt, 'ii', $order_id, $vehicle_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (!mysqli_fetch_assoc($check_result)) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found or unauthorized']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($con);
    exit();
}
mysqli_stmt_close($check_stmt);

// Update status with timestamp
$timestamp_field = '';
if ($new_status === 'picked_up' || $new_status === 'Picked Up') {
    $timestamp_field = ', picked_up_at = NOW()';
} elseif ($new_status === 'delivered' || $new_status === 'Delivered') {
    $timestamp_field = ', delivered_at = NOW()';
}

$query = "UPDATE customer_orders SET status = ?, updated_at = NOW() $timestamp_field WHERE id = ? AND vehicle_id = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'sii', $new_status, $order_id, $vehicle_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Order status updated successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
