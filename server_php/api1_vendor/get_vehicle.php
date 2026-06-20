<?php
/**
 * Get Vehicles
 * Returns all vehicles for a specific vendor
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

// Ensure driver_phone column exists (added after initial table creation)
$con->query("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';

if (empty($firebase_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Firebase UID is required']);
    mysqli_close($con);
    exit();
}

// Get all vehicles for this vendor
$stmt = mysqli_prepare($con, "
    SELECT * FROM vehicles 
    WHERE firebase_uid = ? 
    ORDER BY created_at DESC
");

mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vehicles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vehicles[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'vehicles' => $vehicles,
    'total' => count($vehicles)
]);

mysqli_close($con);
