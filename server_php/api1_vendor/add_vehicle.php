<?php
/**
 * Add Vehicle(s)
 * Allows vendors to add one or multiple vehicles
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

// Ensure driver_phone column exists
$con->query("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    mysqli_close($con);
    exit();
}

$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$vendor_name = isset($data['vendor_name']) ? trim($data['vendor_name']) : '';
$vendor_email = isset($data['vendor_email']) ? trim($data['vendor_email']) : '';
$vendor_phone = isset($data['vendor_phone']) ? trim($data['vendor_phone']) : '';
$vehicles = isset($data['vehicles']) ? $data['vehicles'] : [];

// Fetch vendor info from database if missing
if (empty($vendor_name) || empty($vendor_phone)) {
    $kyc_stmt = mysqli_prepare($con, "SELECT name, email, phone FROM vendor_kyc WHERE firebase_uid = ?");
    mysqli_stmt_bind_param($kyc_stmt, 's', $firebase_uid);
    mysqli_stmt_execute($kyc_stmt);
    $kyc_result = mysqli_stmt_get_result($kyc_stmt);
    if ($kyc_row = mysqli_fetch_assoc($kyc_result)) {
        if (empty($vendor_name)) $vendor_name = $kyc_row['name'] ?? '';
        if (empty($vendor_email)) $vendor_email = $kyc_row['email'] ?? '';
        if (empty($vendor_phone)) $vendor_phone = $kyc_row['phone'] ?? '';
    }
    mysqli_stmt_close($kyc_stmt);
}

// Fallbacks if still empty
if (empty($vendor_name)) $vendor_name = 'Unknown Vendor';
if (empty($vendor_phone)) $vendor_phone = '0000000000';

// Validation
if (empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    mysqli_close($con);
    exit();
}

if (empty($vehicles) || !is_array($vehicles)) {
    echo json_encode(['status' => 'error', 'message' => 'At least one vehicle is required']);
    mysqli_close($con);
    exit();
}

mysqli_begin_transaction($con);

$added_vehicles = [];
$errors = [];

foreach ($vehicles as $index => $vehicle) {
    $vehicle_number = isset($vehicle['vehicle_number']) ? trim($vehicle['vehicle_number']) : '';
    $vehicle_name = isset($vehicle['vehicle_name']) ? trim($vehicle['vehicle_name']) : '';
    $vehicle_year = isset($vehicle['vehicle_year']) ? trim($vehicle['vehicle_year']) : '';
    $vehicle_type = isset($vehicle['vehicle_type']) ? trim($vehicle['vehicle_type']) : '';
    $vehicle_size_feet = isset($vehicle['vehicle_size_feet']) ? trim($vehicle['vehicle_size_feet']) : '';
    $vehicle_tonnage = isset($vehicle['vehicle_tonnage']) ? trim($vehicle['vehicle_tonnage']) : '';
    $driver_name = isset($vehicle['driver_name']) ? trim($vehicle['driver_name']) : '';
    $driver_phone = isset($vehicle['driver_phone']) ? trim($vehicle['driver_phone']) : '';
    $driver_username = isset($vehicle['driver_username']) ? trim($vehicle['driver_username']) : '';
    $driver_password = isset($vehicle['driver_password']) ? trim($vehicle['driver_password']) : '';
    
    if (empty($vehicle_number) || empty($vehicle_name) || empty($vehicle_year) || 
        empty($vehicle_type) || empty($vehicle_size_feet) || empty($vehicle_tonnage) ||
        empty($driver_name) || empty($driver_username) || empty($driver_password)) {
        $errors[] = "Vehicle " . ($index + 1) . ": All fields are required";
        continue;
    }
    
    // Check if vehicle number already exists
    $check_stmt = mysqli_prepare($con, "SELECT id FROM vehicles WHERE vehicle_number = ?");
    mysqli_stmt_bind_param($check_stmt, 's', $vehicle_number);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_fetch_assoc($check_result)) {
        $errors[] = "Vehicle number $vehicle_number already exists";
        mysqli_stmt_close($check_stmt);
        continue;
    }
    mysqli_stmt_close($check_stmt);
    
    // Insert vehicle
    $stmt = mysqli_prepare($con, "
        INSERT INTO vehicles (
            firebase_uid, vendor_name, vendor_email, vendor_phone,
            vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet, vehicle_tonnage,
            driver_name, driver_phone, driver_username, driver_password, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    mysqli_stmt_bind_param($stmt, 'ssssssssssssss',
        $firebase_uid, $vendor_name, $vendor_email, $vendor_phone,
        $vehicle_number, $vehicle_name, $vehicle_year, $vehicle_type, $vehicle_size_feet, $vehicle_tonnage,
        $driver_name, $driver_phone, $driver_username, $driver_password
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $added_vehicles[] = [
            'id' => mysqli_insert_id($con),
            'vehicle_number' => $vehicle_number,
            'vehicle_name' => $vehicle_name
        ];
    } else {
        $errors[] = "Vehicle " . ($index + 1) . ": Failed to add - " . mysqli_error($con);
    }
    
    mysqli_stmt_close($stmt);
}

if (empty($added_vehicles)) {
    mysqli_rollback($con);
    echo json_encode([
        'status' => 'error',
        'message' => 'No vehicles were added',
        'errors' => $errors
    ]);
    mysqli_close($con);
    exit();
}

mysqli_commit($con);

echo json_encode([
    'status' => 'success',
    'message' => count($added_vehicles) . ' vehicle(s) added successfully',
    'added_vehicles' => $added_vehicles,
    'errors' => !empty($errors) ? $errors : null
]);

mysqli_close($con);
