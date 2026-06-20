<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$online_filter = isset($_GET['online']) ? trim($_GET['online']) : '';

// Build query
$query = "
    SELECT 
        id,
        firebase_uid,
        vendor_name,
        vendor_email,
        vendor_phone,
        vehicle_number,
        vehicle_name,
        vehicle_year,
        vehicle_type,
        vehicle_size_feet,
        driver_name,
        driver_phone,
        driver_username,
        status,
        is_online,
        last_latitude,
        last_longitude,
        last_location_update,
        location_address,
        created_at,
        updated_at
    FROM vehicles 
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($online_filter === 'online') {
    $query .= " AND is_online = 1";
} elseif ($online_filter === 'offline') {
    $query .= " AND is_online = 0";
}

$query .= " ORDER BY is_online DESC, last_location_update DESC, created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    $stmt = mysqli_prepare($con, $query);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vehicles = [];
$online_count = 0;
$offline_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    // Count online/offline
    if ($row['is_online'] == 1) {
        $online_count++;
    } else {
        $offline_count++;
    }
    
    $vehicles[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'vehicles' => $vehicles,
    'total' => count($vehicles),
    'online_count' => $online_count,
    'offline_count' => $offline_count
]);

mysqli_close($con);
