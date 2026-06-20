<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$assignment_id = isset($data['assignment_id']) ? intval($data['assignment_id']) : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if (empty($firebase_uid) || empty($assignment_id) || empty($status)) {
    echo json_encode(array('status' => 'error', 'message' => 'Missing required fields'));
    exit();
}

// Map frontend status 'rejected' to database ENUM 'cancelled'
$db_status = ($status === 'rejected') ? 'cancelled' : $status;

// 1. Get the al_number and phone
$al_number = '';
$stmt = $con->prepare("SELECT al_number FROM fleet_assignments WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $al_number = $row['al_number'];
}
$stmt->close();

if (empty($al_number)) {
    echo json_encode(array('status' => 'error', 'message' => 'Assignment not found'));
    exit();
}

// Get vendor phone
$vendor_phone = '';
$vk_stmt = $con->prepare("SELECT phone FROM vendor_kyc WHERE firebase_uid = ? LIMIT 1");
if ($vk_stmt) {
    $vk_stmt->bind_param("s", $firebase_uid);
    $vk_stmt->execute();
    $vk_res = $vk_stmt->get_result();
    if ($vk_row = $vk_res->fetch_assoc()) {
        $vendor_phone = $vk_row['phone'];
    }
    $vk_stmt->close();
}

// 2. Update the fleet assignments using robust matching
$query = "UPDATE fleet_assignments 
          SET status = ?, updated_at = NOW() 
          WHERE TRIM(al_number) = TRIM(?)";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "ss", $db_status, $al_number);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0 || mysqli_stmt_errno($stmt) == 0) {
    
    // 3. If Declined/Rejected, revert courier shipments back to 'AWB Created'
    if ($db_status === 'cancelled') {
        // Update courier status from any status back to 'AWB Created' so it can be reassigned
        $c_stmt = $con->prepare("UPDATE courier SET status = 'AWB Created', assigned_vehicle = '' WHERE TRIM(assigned_vehicle) = TRIM(?)");
        $c_stmt->bind_param("s", $al_number);
        $c_stmt->execute();
        $c_stmt->close();
    }
    
    echo json_encode(array('status' => 'success', 'message' => 'Assignment status updated successfully'));
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Failed to update or unauthorized'));
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>
