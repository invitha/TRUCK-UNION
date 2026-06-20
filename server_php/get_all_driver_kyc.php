<?php
// Get all driver KYC submissions for admin panel
require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit();
    }
    
    $con->set_charset('utf8mb4');
    
    // Get all driver KYC data
    $query = "SELECT * FROM driver_kyc ORDER BY created_at DESC";
    $result = mysqli_query($con, $query);
    
    $kyc_list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $kyc_list[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'kyc_list' => $kyc_list,
        'total' => count($kyc_list)
    ]);
    
    mysqli_close($con);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
