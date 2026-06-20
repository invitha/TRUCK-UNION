<?php
/**
 * Update Vehicle
 * Updates vehicle information
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

$id = isset($data['id']) ? intval($data['id']) : 0;
$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$vehicle_number = isset($data['vehicle_number']) ? trim($data['vehicle_number']) : '';
$vehicle_name = isset($data['vehicle_name']) ? trim($data['vehicle_name']) : '';
$vehicle_year = isset($data['vehicle_year']) ? trim($data['vehicle_year']) : '';
$vehicle_type = isset($data['vehicle_type']) ? trim($data['vehicle_type']) : '';
$vehicle_size_feet = isset($data['vehicle_size_feet']) ? trim($data['vehicle_size_feet']) : '';
$driver_name = isset($data['driver_name']) ? trim($data['driver_name']) : '';
$driver_phone = isset($data['driver_phone']) ? trim($data['driver_phone']) : '';
$driver_username = isset($data['driver_username']) ? trim($data['driver_username']) : '';
$driver_password = isset($data['driver_password']) ? trim($data['driver_password']) : '';

if (empty($id) || empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID and Firebase UID are required']);
    mysqli_close($con);
    exit();
}

// Check if vehicle belongs to this user
$check_stmt = mysqli_prepare($con, "SELECT id FROM vehicles WHERE id = ? AND firebase_uid = ?");
mysqli_stmt_bind_param($check_stmt, 'is', $id, $firebase_uid);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (!mysqli_fetch_assoc($check_result)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle not found or unauthorized']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($con);
    exit();
}
mysqli_stmt_close($check_stmt);

// Check if new vehicle number already exists (excluding current vehicle)
$check_number_stmt = mysqli_prepare($con, "SELECT id FROM vehicles WHERE vehicle_number = ? AND id != ?");
mysqli_stmt_bind_param($check_number_stmt, 'si', $vehicle_number, $id);
mysqli_stmt_execute($check_number_stmt);
$check_number_result = mysqli_stmt_get_result($check_number_stmt);

if (mysqli_fetch_assoc($check_number_result)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle number already exists']);
    mysqli_stmt_close($check_number_stmt);
    mysqli_close($con);
    exit();
}
mysqli_stmt_close($check_number_stmt);

// Update vehicle
$stmt = mysqli_prepare($con, "
    UPDATE vehicles SET
        vehicle_number = ?,
        vehicle_name = ?,
        vehicle_year = ?,
        vehicle_type = ?,
        vehicle_size_feet = ?,
        driver_name = ?,
        driver_phone = ?,
        driver_username = ?,
        driver_password = ?,
        updated_at = NOW()
    WHERE id = ? AND firebase_uid = ?
");

mysqli_stmt_bind_param($stmt, 'sssssssssis',
    $vehicle_number, $vehicle_name, $vehicle_year, $vehicle_type, $vehicle_size_feet,
    $driver_name, $driver_phone, $driver_username, $driver_password,
    $id, $firebase_uid
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Vehicle updated successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update vehicle: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
