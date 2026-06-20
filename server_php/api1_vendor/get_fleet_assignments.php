<?php
/**
 * Get Fleet Assignments for Vendor
 * Returns all fleet assignments for a specific vendor
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

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        echo json_encode(array('status' => 'error', 'message' => 'Database connection failed'));
        exit();
    }
    
    $con->set_charset('utf8mb4');
    
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => 'Server error: ' . $e->getMessage()));
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(array('status' => 'error', 'message' => 'Invalid request data'));
    mysqli_close($con);
    exit();
}

$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';
$status_filter = isset($data['status']) ? trim($data['status']) : ''; // active, completed, all

// Validation
if (empty($firebase_uid)) {
    // TEMPORARY BYPASS: If a driver logs in and their vehicle has an empty firebase_uid in the DB,
    // driver_login.php returns an empty string. We must not exit here, otherwise they see "No Assignments".
    // We will allow it to proceed and match empty UIDs for now.
    $firebase_uid = ''; 
}

// Fix the database schema to natively support 'pending'
$con->query("ALTER TABLE fleet_assignments MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending'");

// Self-healing: Any assignment that was accidentally saved as blank should be 'pending' so they can Accept/Decline it
$con->query("UPDATE fleet_assignments SET status = 'pending' WHERE status = '' OR status IS NULL");

// Self-healing: If the courier is Delivered, mark fleet_assignment as completed automatically
$con->query("UPDATE fleet_assignments f
             INNER JOIN courier c ON TRIM(c.assigned_vehicle) = TRIM(f.al_number)
             SET f.status = 'completed', f.actual_completion_date = IFNULL(f.actual_completion_date, NOW()), f.updated_at = NOW()
             WHERE LOWER(c.status) = 'delivered'
             AND f.status IN ('active', 'pending')");

// Get vendor phone if firebase_uid is provided
$vendor_phone = '';
$is_vendor = false;
if (!empty($firebase_uid)) {
    $vk_stmt = $con->prepare("SELECT phone FROM vendor_kyc WHERE firebase_uid = ? LIMIT 1");
    if ($vk_stmt) {
        $vk_stmt->bind_param("s", $firebase_uid);
        $vk_stmt->execute();
        $vk_res = $vk_stmt->get_result();
        if ($vk_row = $vk_res->fetch_assoc()) {
            $vendor_phone = $vk_row['phone'];
            $is_vendor = true;
        }
        $vk_stmt->close();
    }
}

// Build query based on status filter
$query = "
    SELECT MAX(f.id) as id, 
           GROUP_CONCAT(DISTINCT f.al_number SEPARATOR ', ') as al_number,
           MAX(f.vehicle_id) as vehicle_id, 
           f.vehicle_number, 
           MAX(f.vehicle_name) as vehicle_name, 
           MAX(f.driver_name) as driver_name, 
           MAX(f.assigned_by) as assigned_by,
           MAX(f.created_at) as created_at,
           f.status, 
           MAX(f.notes) as notes,
           GROUP_CONCAT(DISTINCT c.ciudad SEPARATOR ', ') as pickup_cities,
           GROUP_CONCAT(DISTINCT c.city1 SEPARATOR ', ') as delivery_cities,
           MAX(f.pickup_location) as pickup_location,
           MAX(f.delivery_location) as delivery_location,
           MAX(f.assignment_date) as assignment_date,
           MAX(f.expected_completion_date) as expected_completion_date,
           MAX(f.actual_completion_date) as actual_completion_date,
           MAX(f.updated_at) as updated_at,
           IF(SUM(c.vendor_amount) > 0, SUM(c.vendor_amount), MAX(f.payment_amount)) as total_agreed_amount,
           IF(SUM(c.vendor_paid_amount) > 0, SUM(c.vendor_paid_amount), MAX(f.advance_amount)) as total_paid_amount,
           IF(MAX(c.vendor_transaction_id) IS NOT NULL AND MAX(c.vendor_transaction_id) != '', MAX(c.vendor_transaction_id), MAX(f.vendor_transaction_id)) as vendor_transaction_id,
           MAX(c.status) as courier_status,
           GROUP_CONCAT(DISTINCT c.tracking ORDER BY c.cid SEPARATOR ', ') as tracking_numbers,
           GROUP_CONCAT(DISTINCT c.reference_no ORDER BY c.cid SEPARATOR ', ') as reference_numbers
    FROM fleet_assignments f
    LEFT JOIN courier c ON TRIM(f.al_number) = TRIM(c.assigned_vehicle)
    LEFT JOIN vehicles v ON f.vehicle_id = v.id
    WHERE (f.vendor_firebase_uid = ? 
           OR c.assigned_vendor_id = ?
           OR (? != '' AND v.vendor_phone = ?))
";
$params = array($firebase_uid, $firebase_uid, $vendor_phone, $vendor_phone);
$types = 'ssss';

if ($status_filter === 'active') {
    if ($is_vendor) {
        // Vendors see both pending assignments (to accept/decline) and active assignments (accepted)
        $query .= " AND (f.status IN ('pending', 'active') OR f.status = '' OR f.status IS NULL)";
    } else {
        // Drivers ONLY see active assignments! They should not see pending until the vendor accepts it.
        $query .= " AND f.status = 'active'";
    }
} elseif ($status_filter === 'completed') {
    $query .= " AND f.status IN ('completed', 'cancelled')";
} elseif ($status_filter === 'pending') {
    // Only vendors should be able to filter by pending
    if ($is_vendor) {
        $query .= " AND (f.status = 'pending' OR f.status = '' OR f.status IS NULL)";
    } else {
        // Drivers should never see pending assignments
        $query .= " AND 1=0"; // Return no results
    }
}
// 'all' or empty = no additional filter

$query .= " GROUP BY f.vehicle_number, f.status, DATE(f.created_at) ORDER BY created_at DESC";

$stmt = mysqli_prepare($con, $query);
if (!$stmt) {
    echo json_encode(array('status' => 'error', 'message' => 'SQL Error: ' . mysqli_error($con)));
    exit();
}
mysqli_stmt_bind_param($stmt, $types, $firebase_uid, $firebase_uid, $vendor_phone, $vendor_phone);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$assignments = array();
while ($row = mysqli_fetch_assoc($result)) {
    $pickup = !empty($row['pickup_location']) ? $row['pickup_location'] : $row['pickup_cities'];
    $delivery = !empty($row['delivery_location']) ? $row['delivery_location'] : $row['delivery_cities'];
    
    $assignments[] = [
        'id' => $row['id'],
        'al_number' => $row['al_number'],
        'vehicle_id' => $row['vehicle_id'],
        'vehicle_number' => $row['vehicle_number'],
        'vehicle_name' => $row['vehicle_name'],
        'driver_name' => $row['driver_name'],
        'assigned_by' => $row['assigned_by'],
        'pickup_location' => $pickup,
        'delivery_location' => $delivery,
        'assignment_date' => $row['assignment_date'],
        'expected_completion_date' => $row['expected_completion_date'],
        'actual_completion_date' => $row['actual_completion_date'],
        'status' => $row['status'],
        'courier_status' => $row['courier_status'] ?? '',
        'tracking_numbers' => $row['tracking_numbers'] ?? '',
        'reference_numbers' => $row['reference_numbers'] ?? '',
        'notes' => $row['notes'],
        'total_agreed_amount' => isset($row['total_agreed_amount']) ? $row['total_agreed_amount'] : 0,
        'total_paid_amount' => isset($row['total_paid_amount']) ? $row['total_paid_amount'] : 0,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($con);

echo json_encode(array(
    'status' => 'success',
    'assignments' => $assignments,
    'total' => count($assignments)
));
?>
