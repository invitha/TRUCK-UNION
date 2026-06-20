<?php
/**
 * Driver Login
 * Authenticates driver using username and password created by vendor
 */

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

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    mysqli_close($con);
    exit();
}

$driver_username = isset($data['driver_username']) ? trim($data['driver_username']) : '';
$driver_password = isset($data['driver_password']) ? trim($data['driver_password']) : '';

// Validation
if (empty($driver_username) || empty($driver_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
    mysqli_close($con);
    exit();
}

// Find driver by username and password
$stmt = mysqli_prepare($con, "
    SELECT *
    FROM vehicles 
    WHERE driver_username = ? AND driver_password = ? AND status = 'active'
");

mysqli_stmt_bind_param($stmt, 'ss', $driver_username, $driver_password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$driver = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($driver) {
    // Login successful
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'driver' => [
            'id' => $driver['id'],
            'driver_name' => $driver['driver_name'],
            'driver_mobile' => isset($driver['driver_phone']) ? $driver['driver_phone'] : $driver['vendor_phone'],
            'driver_email' => isset($driver['driver_email']) && !empty($driver['driver_email'])
                                ? $driver['driver_email']
                                : (isset($driver['vendor_email']) ? $driver['vendor_email'] : null),
            'driver_username' => $driver['driver_username'],
            'vehicle_id' => $driver['id'],
            'vehicle_number' => $driver['vehicle_number'],
            'vehicle_name' => $driver['vehicle_name'],
            'vehicle_type' => $driver['vehicle_type'],
            'vehicle_size_feet' => $driver['vehicle_size_feet'],
            'vendor_name' => $driver['vendor_name'],
            'vendor_phone' => $driver['vendor_phone'],
            'firebase_uid' => $driver['firebase_uid']
        ]
    ]);
} else {
    // Login failed
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid username or password'
    ]);
}

mysqli_close($con);
