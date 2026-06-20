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
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
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

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$online_filter = isset($_GET['online']) ? trim($_GET['online']) : '';

// Build query - only select columns that exist
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
        driver_username,
        status,
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

$query .= " ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    $stmt = mysqli_prepare($con, $query);
}

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . mysqli_error($con)]);
    mysqli_close($con);
    exit();
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vehicles = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Add default values for location fields
    $row['is_online'] = 0;
    $row['last_latitude'] = null;
    $row['last_longitude'] = null;
    $row['last_location_update'] = null;
    $row['location_address'] = null;
    
    $vehicles[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'vehicles' => $vehicles,
    'total' => count($vehicles),
    'online_count' => 0,
    'offline_count' => count($vehicles)
]);

mysqli_close($con);
