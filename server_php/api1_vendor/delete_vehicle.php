<?php
/**
 * Delete Vehicle
 * Removes a vehicle from the system
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

if (empty($id) || empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID and Firebase UID are required']);
    mysqli_close($con);
    exit();
}

// Check if vehicle belongs to this user and get vehicle info
$check_stmt = mysqli_prepare($con, "SELECT id, vehicle_number, vehicle_name FROM vehicles WHERE id = ? AND firebase_uid = ?");
mysqli_stmt_bind_param($check_stmt, 'is', $id, $firebase_uid);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$vehicle = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if (!$vehicle) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle not found or unauthorized']);
    mysqli_close($con);
    exit();
}

// Delete vehicle
$stmt = mysqli_prepare($con, "DELETE FROM vehicles WHERE id = ? AND firebase_uid = ?");
mysqli_stmt_bind_param($stmt, 'is', $id, $firebase_uid);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Vehicle deleted successfully',
        'vehicle_number' => $vehicle['vehicle_number'],
        'vehicle_name' => $vehicle['vehicle_name']
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete vehicle: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
