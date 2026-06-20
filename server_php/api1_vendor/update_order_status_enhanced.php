<?php
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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    mysqli_close($con);
    exit();
}

$al_number = isset($data['al_number']) ? trim($data['al_number']) : '';
$vehicle_id = isset($data['vehicle_id']) ? intval($data['vehicle_id']) : 0;
$new_status = isset($data['status']) ? trim($data['status']) : '';
$driver_notes = isset($data['driver_notes']) ? trim($data['driver_notes']) : null;
$latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
$longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;

if (empty($al_number) || empty($vehicle_id) || empty($new_status)) {
    echo json_encode(['status' => 'error', 'message' => 'AL Number, Vehicle ID, and status are required']);
    mysqli_close($con);
    exit();
}

// Verify vehicle owns this AL number (works for both pending and active assignments)
$check_stmt = mysqli_prepare($con, "SELECT id FROM fleet_assignments WHERE al_number = ? AND vehicle_id = ? AND status IN ('active','pending')");
mysqli_stmt_bind_param($check_stmt, 'si', $al_number, $vehicle_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$assignment = mysqli_fetch_assoc($check_result);

if (!$assignment) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or invalid AL Number for this vehicle']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($con);
    exit();
}
mysqli_stmt_close($check_stmt);

// Update all shipments in `courier` table matching this AL number
$query = "UPDATE courier SET status = ?, comments = COALESCE(?, comments) WHERE assigned_vehicle = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'sss', $new_status, $driver_notes, $al_number);

if (mysqli_stmt_execute($stmt)) {

    // Add to courier_track timeline so Admin CRM can see it!
    // Customer-friendly remarks map
    $status_messages = [
        'pickup assigned'               => 'Your shipment has been assigned to a delivery partner.',
        'pickup accepted'               => 'Our delivery partner has accepted your shipment.',
        'pickup declined'               => 'Pickup could not be completed. Our team will contact you shortly.',
        'failed pickup'                 => 'Pickup attempt was unsuccessful. Our team will follow up.',
        'picked up'                     => 'Your shipment has been picked up successfully.',
        'reaching at loading point'     => 'Your consignment is reaching the loading point.',
        'reached at loading point'      => 'Your consignment has reached the loading point.',
        'in transit'                    => 'Your consignment is in transit and on its way.',
        'reaching at unloading point'   => 'Your consignment is reaching the unloading point.',
        'reached at unloading point'    => 'Your consignment has reached the unloading point.',
        'out for delivery'              => 'Your consignment is out for delivery.',
        'delivered'                     => 'Your consignment has been delivered successfully. Thank you!',
        'failed delivery'               => 'Delivery attempt was unsuccessful. Our team will contact you.',
        'return initiated'              => 'A return has been initiated for your shipment.',
        'return in transit'             => 'Your return shipment is in transit.',
        'return delivered'              => 'Your return shipment has been delivered.',
    ];
    $status_key    = strtolower(trim($new_status));
    $friendly_msg  = $status_messages[$status_key] ?? "Your consignment status has been updated to: $new_status.";
    $comment_track = $friendly_msg . ($driver_notes ? " Note: $driver_notes" : "");
    $insert_track = "INSERT INTO courier_track (cid, cons_no, letra, pick_time, status, detailed_status, comments, ship_name, phone, correo, user, bk_time)
                     SELECT cid, tracking, letra, '00:00', ?, ?, ?, ship_name, phone, correo, 'Driver App', CURRENT_TIMESTAMP
                     FROM courier WHERE assigned_vehicle = ?";
    $stmt_track = mysqli_prepare($con, $insert_track);
    mysqli_stmt_bind_param($stmt_track, 'ssss', $new_status, $new_status, $comment_track, $al_number);
    mysqli_stmt_execute($stmt_track);
    mysqli_stmt_close($stmt_track);

    // If marked Delivered, update fleet_assignments to 'completed'
    if (strtolower($new_status) === 'delivered') {
        $update_fleet = "UPDATE fleet_assignments SET status = 'completed', actual_completion_date = NOW() WHERE al_number = ? AND vehicle_id = ?";
        $stmt_fleet = mysqli_prepare($con, $update_fleet);
        mysqli_stmt_bind_param($stmt_fleet, 'si', $al_number, $vehicle_id);
        mysqli_stmt_execute($stmt_fleet);
        mysqli_stmt_close($stmt_fleet);
    }

    // If driver declines, reset fleet_assignment to 'pending' so internal team can reassign
    if (strtolower($new_status) === 'pickup declined') {
        $update_fleet = "UPDATE fleet_assignments SET status = 'pending', vehicle_id = NULL, updated_at = NOW() WHERE al_number = ? AND vehicle_id = ?";
        $stmt_fleet = mysqli_prepare($con, $update_fleet);
        mysqli_stmt_bind_param($stmt_fleet, 'si', $al_number, $vehicle_id);
        mysqli_stmt_execute($stmt_fleet);
        mysqli_stmt_close($stmt_fleet);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Order status updated successfully'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>
