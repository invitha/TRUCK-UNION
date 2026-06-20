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

$vehicle_id = isset($data['vehicle_id']) ? intval($data['vehicle_id']) : 0;
$status_filter = isset($data['status']) ? trim($data['status']) : '';

if (empty($vehicle_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID is required']);
    mysqli_close($con);
    exit();
}

// Build query based on status filter
if (!empty($status_filter)) {
    $stmt = mysqli_prepare($con, "
        SELECT * FROM customer_orders 
        WHERE vehicle_id = ? AND status = ?
        ORDER BY book_date DESC, created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, 'is', $vehicle_id, $status_filter);
} else {
    $stmt = mysqli_prepare($con, "
        SELECT * FROM customer_orders 
        WHERE vehicle_id = ?
        ORDER BY book_date DESC, created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'orders' => $orders,
    'total' => count($orders)
]);

mysqli_close($con);
