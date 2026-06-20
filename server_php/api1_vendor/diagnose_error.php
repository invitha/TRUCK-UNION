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

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        echo json_encode([
            'status' => 'error',
            'step' => 'connection',
            'message' => 'Connection failed: ' . $con->connect_error
        ]);
        exit();
    }
    
    $con->set_charset('utf8mb4');
    
    // Check if vendor_kyc table exists
    $result = $con->query("SHOW TABLES LIKE 'vendor_kyc'");
    $vendor_kyc_exists = $result && $result->num_rows > 0;
    
    // Check if notifications table exists
    $result2 = $con->query("SHOW TABLES LIKE 'notifications'");
    $notifications_exists = $result2 && $result2->num_rows > 0;
    
    // Get all tables
    $tables_result = $con->query("SHOW TABLES");
    $tables = [];
    while ($row = $tables_result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo json_encode([
        'status' => 'success',
        'database' => $dbname,
        'vendor_kyc_table_exists' => $vendor_kyc_exists,
        'notifications_table_exists' => $notifications_exists,
        'all_tables' => $tables
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
