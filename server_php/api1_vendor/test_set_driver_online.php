<?php
// Test file to manually set driver online with location
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);

if ($con->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

// Set vehicle ID 1 online with test location (Hyderabad coordinates)
$vehicle_id = 1;
$latitude = 17.385044;
$longitude = 78.486671;
$address = 'Hyderabad, Telangana, India';

$query = "UPDATE vehicles 
          SET is_online = 1,
              last_latitude = ?,
              last_longitude = ?,
              last_location_update = NOW(),
              location_address = ?
          WHERE id = ?";

$stmt = $con->prepare($query);
$stmt->bind_param('ddsi', $latitude, $longitude, $address, $vehicle_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Driver set to online with location',
        'vehicle_id' => $vehicle_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'address' => $address
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update: ' . $con->error]);
}

$stmt->close();
$con->close();
