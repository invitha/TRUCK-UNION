<?php
/**
 * Get Shipment History / Timeline for a tracking number
 * Returns courier_track entries ordered by time (oldest first)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

error_reporting(E_ALL);
ini_set('display_errors', 0);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit();
}
$con->set_charset('utf8mb4');

// Accept tracking via GET or POST JSON
$tracking = '';
if (!empty($_GET['tracking'])) {
    $tracking = trim($_GET['tracking']);
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    $tracking = trim($body['tracking'] ?? '');
}

if (empty($tracking)) {
    echo json_encode(['status' => 'error', 'message' => 'tracking is required']);
    exit();
}

$s_tracking = $con->real_escape_string($tracking);

// Fetch timeline from courier_track (status history)
// Also join to get courier basic info
$history = [];
$q = $con->query("
    SELECT ct.status, ct.detailed_status, ct.comments, ct.bk_time, ct.user
    FROM courier_track ct
    WHERE ct.cons_no = '$s_tracking'
       OR ct.cons_no IN (SELECT tracking FROM courier WHERE assigned_vehicle = '$s_tracking')
    ORDER BY ct.bk_time ASC
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $history[] = [
            'status'          => $row['status'] ?? '',
            'detailed_status' => $row['detailed_status'] ?? '',
            'remarks'         => $row['comments'] ?? '',
            'time'            => $row['bk_time'] ?? '',
            'updated_by'      => $row['user'] ?? '',
        ];
    }
}

// Also include tsp_milestones for driver steps
$ms_q = $con->query("
    SELECT trStatus as status, trLatitude, trLongitude, created_at as time
    FROM tsp_milestones
    WHERE tracking = '$s_tracking'
    ORDER BY trID ASC
");
$milestones = [];
if ($ms_q) {
    while ($row = $ms_q->fetch_assoc()) {
        $milestones[] = [
            'status' => $row['status'] ?? '',
            'time'   => $row['time']   ?? '',
            'lat'    => $row['trLatitude']  ?? null,
            'lng'    => $row['trLongitude'] ?? null,
        ];
    }
}

// Get courier basic info
$info = null;
$ci = $con->query("SELECT tracking, assigned_vehicle, ship_name, s_add, rev_name, r_add, status, book_date FROM courier WHERE tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking' LIMIT 1");
if ($ci && $row = $ci->fetch_assoc()) {
    $info = $row;
}

$con->close();

echo json_encode([
    'status'     => 'success',
    'tracking'   => $tracking,
    'courier'    => $info,
    'history'    => $history,
    'milestones' => $milestones,
]);
?>
