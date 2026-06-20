<?php
/**
 * Get Driver Live Location
 * Returns the latest GPS coordinates for a vehicle/driver
 * Used by the vendor to track driver in real time
 *
 * POST params:
 *   - vehicle_id : int
 *   - firebase_uid : string (vendor, for auth check)
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

if ($vehicle_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'vehicle_id is required']);
    mysqli_close($con);
    exit();
}

$stmt = $con->prepare("
    SELECT 
        id,
        vehicle_number,
        vehicle_name,
        driver_name,
        last_latitude,
        last_longitude,
        last_location_update,
        location_address,
        is_online
    FROM vehicles
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param('i', $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
mysqli_close($con);

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle not found']);
    exit();
}

$has_location = !empty($row['last_latitude']) && !empty($row['last_longitude']);

echo json_encode([
    'status'           => 'success',
    'vehicle_id'       => $row['id'],
    'vehicle_number'   => $row['vehicle_number'],
    'vehicle_name'     => $row['vehicle_name'],
    'driver_name'      => $row['driver_name'],
    'latitude'         => $has_location ? floatval($row['last_latitude'])  : null,
    'longitude'        => $has_location ? floatval($row['last_longitude']) : null,
    'last_updated'     => $row['last_location_update'],
    'address'          => $row['location_address'] ?? '',
    'is_online'        => intval($row['is_online']) === 1,
    'has_location'     => $has_location,
]);
