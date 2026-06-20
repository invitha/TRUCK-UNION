<?php
// Check if location columns exist and add them if missing
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);

if ($con->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $con->connect_error]));
}

$con->set_charset('utf8mb4');

// Check if location columns exist
$result = $con->query("SHOW COLUMNS FROM vehicles LIKE 'is_online'");
$has_columns = $result->num_rows > 0;

if ($has_columns) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Location columns already exist',
        'columns_exist' => true
    ]);
} else {
    // Add the columns
    $sql = "
    ALTER TABLE `vehicles` 
    ADD COLUMN `is_online` TINYINT(1) DEFAULT 0 AFTER `status`,
    ADD COLUMN `last_latitude` DECIMAL(10, 8) NULL AFTER `is_online`,
    ADD COLUMN `last_longitude` DECIMAL(11, 8) NULL AFTER `last_latitude`,
    ADD COLUMN `last_location_update` TIMESTAMP NULL AFTER `last_longitude`,
    ADD COLUMN `location_address` VARCHAR(500) NULL AFTER `last_location_update`,
    ADD INDEX `idx_is_online` (`is_online`),
    ADD INDEX `idx_last_location_update` (`last_location_update`)
    ";
    
    if ($con->query($sql)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Location columns added successfully',
            'columns_exist' => false,
            'columns_added' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to add columns: ' . $con->error,
            'columns_exist' => false,
            'columns_added' => false
        ]);
    }
}

$con->close();
