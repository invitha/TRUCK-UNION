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
$latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
$longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;
$address = isset($data['address']) ? trim($data['address']) : '';
$is_online = isset($data['is_online']) ? intval($data['is_online']) : 1;

if (empty($vehicle_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID is required']);
    mysqli_close($con);
    exit();
}

// Update vehicle location and online status
$stmt = mysqli_prepare($con, "
    UPDATE vehicles 
    SET is_online = ?,
        last_latitude = ?,
        last_longitude = ?,
        last_location_update = NOW(),
        location_address = ?
    WHERE id = ?
");

mysqli_stmt_bind_param($stmt, 'iddsi', $is_online, $latitude, $longitude, $address, $vehicle_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Location updated successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update location: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
