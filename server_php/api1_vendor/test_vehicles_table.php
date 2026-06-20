<?php
// Simple test to check vehicles table
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

// Test 1: Connection
$con = new mysqli($host, $username, $password, $dbname);

if ($con->connect_error) {
    die(json_encode([
        'status' => 'error',
        'test' => 'connection',
        'message' => 'Connection failed: ' . $con->connect_error
    ]));
}

// Test 2: Check if vehicles table exists
$result = $con->query("SHOW TABLES LIKE 'vehicles'");
if ($result->num_rows == 0) {
    die(json_encode([
        'status' => 'error',
        'test' => 'table_exists',
        'message' => 'vehicles table does not exist'
    ]));
}

// Test 3: Check table structure
$result = $con->query("DESCRIBE vehicles");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

// Test 4: Count vehicles
$result = $con->query("SELECT COUNT(*) as total FROM vehicles");
$count = $result->fetch_assoc()['total'];

// Test 5: Get one vehicle
$result = $con->query("SELECT * FROM vehicles LIMIT 1");
$sample = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'connection' => 'OK',
    'table_exists' => 'YES',
    'columns' => $columns,
    'total_vehicles' => $count,
    'sample_vehicle' => $sample,
    'has_location_columns' => in_array('is_online', $columns)
]);

$con->close();
