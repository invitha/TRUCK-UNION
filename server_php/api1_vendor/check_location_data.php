<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);

if ($con->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

$result = $con->query("SELECT id, vehicle_number, is_online, last_latitude, last_longitude, location_address, last_location_update FROM vehicles WHERE id = 1");

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'data' => $row
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data found']);
}

$con->close();
