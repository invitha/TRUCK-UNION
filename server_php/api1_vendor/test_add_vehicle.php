<?php
/**
 * Test Vehicle Table Setup
 * Checks if vehicles table exists and has correct structure
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection - SAME AS OTHER WORKING FILES
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

// Check if vehicles table exists
$result = mysqli_query($con, "SHOW TABLES LIKE 'vehicles'");
$tableExists = mysqli_num_rows($result) > 0;

if (!$tableExists) {
    echo json_encode([
        'status' => 'error',
        'message' => 'vehicles table does not exist',
        'solution' => 'Run create_vehicles_table.sql in phpMyAdmin'
    ]);
    mysqli_close($con);
    exit();
}

// Get table structure
$result = mysqli_query($con, "DESCRIBE vehicles");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = [
        'Field' => $row['Field'],
        'Type' => $row['Type'],
        'Null' => $row['Null'],
        'Key' => $row['Key'],
        'Default' => $row['Default']
    ];
}

// Count existing vehicles
$count_result = mysqli_query($con, "SELECT COUNT(*) as total FROM vehicles");
$count_row = mysqli_fetch_assoc($count_result);
$total_vehicles = $count_row['total'];

echo json_encode([
    'status' => 'success',
    'message' => 'vehicles table exists and is ready',
    'database' => $dbname,
    'total_vehicles' => $total_vehicles,
    'columns' => $columns
], JSON_PRETTY_PRINT);

mysqli_close($con);
