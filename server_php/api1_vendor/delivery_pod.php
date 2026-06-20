<?php
/**
 * Delivery POD (Proof of Delivery) Upload
 * 
 * Mirrors abra_logistics C# endpoint: POST /api/consignment/{id}/pod
 * Same logic: updates courier to 'Delivered' + inserts POD row into tsp_milestones
 * 
 * Required POST (multipart/form-data):
 *   - tracking             : courier tracking number (AL number)
 *   - al_number            : AL number
 *   - vehicle_id           : vehicle ID
 *   - deliveryDriverId     : driver's delivery_id
 *   - receiverName         : receiver's name
 *   - receiverPhoneNumber  : receiver's phone
 *   - latitude             : GPS latitude
 *   - longitude            : GPS longitude
 *   - scannedBarcode       : (optional) scanned barcode
 *   - PODPhoto             : (file) the POD photo
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
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

// ── Self-heal: ensure POD columns exist on courier table ─────────────────
$pod_cols = [
    "delivery_pod_image     TEXT NULL",
    "delivery_pod_timestamp DATETIME NULL",
    "delivery_latitude      DECIMAL(10,7) NULL",
    "delivery_longitude     DECIMAL(10,7) NULL",
    "delivery_driver_id     VARCHAR(255) NULL",
    "receiver_name_pod      VARCHAR(255) NULL",
    "receiver_phone_pod     VARCHAR(50)  NULL",
    "scanned_barcode        VARCHAR(255) NULL",
    "rev_name               VARCHAR(255) NULL",
    "r_phone                VARCHAR(50)  NULL",
    "detailed_status        VARCHAR(255) NULL",
    "last_updated           DATETIME NULL",
];
foreach ($pod_cols as $col_def) {
    $col_name = trim(explode(' ', $col_def)[0]);
    $chk = $con->query("SHOW COLUMNS FROM courier LIKE '$col_name'");
    if ($chk && $chk->num_rows == 0) {
        @$con->query("ALTER TABLE courier ADD COLUMN $col_def");
    }
}

// ── Self-heal: ensure tsp_milestones exists ───────────────────────────────
$con->query("CREATE TABLE IF NOT EXISTS tsp_milestones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id    VARCHAR(255),
    tracking       VARCHAR(255),
    trStatus       VARCHAR(100),
    trLatitude     DECIMAL(10,7),
    trLongitude    DECIMAL(10,7),
    trPODimage     TEXT,
    created_at     DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Validate required fields ─────────────────────────────────────────────
$tracking        = isset($_POST['tracking'])            ? trim($_POST['tracking'])            : '';
$al_number       = isset($_POST['al_number'])           ? trim($_POST['al_number'])           : $tracking;
$vehicle_id      = isset($_POST['vehicle_id'])          ? intval($_POST['vehicle_id'])        : 0;
$delivery_driver = isset($_POST['deliveryDriverId'])    ? trim($_POST['deliveryDriverId'])    : '';
$receiver_name   = isset($_POST['receiverName'])        ? trim($_POST['receiverName'])        : '';
$receiver_phone  = isset($_POST['receiverPhoneNumber']) ? trim($_POST['receiverPhoneNumber']) : '';
$latitude        = isset($_POST['latitude'])            ? trim($_POST['latitude'])            : null;
$longitude       = isset($_POST['longitude'])           ? trim($_POST['longitude'])           : null;
$scanned_barcode = isset($_POST['scannedBarcode'])      ? trim($_POST['scannedBarcode'])      : null;

$tracking = !empty($tracking) ? $tracking : $al_number;

if (empty($tracking)) {
    echo json_encode(['status' => 'error', 'message' => 'tracking / al_number is required']);
    mysqli_close($con);
    exit();
}

if (empty($receiver_name) || empty($receiver_phone)) {
    echo json_encode(['status' => 'error', 'message' => 'receiverName and receiverPhoneNumber are required']);
    mysqli_close($con);
    exit();
}

// ── Validate file upload ─────────────────────────────────────────────────
if (!isset($_FILES['PODPhoto']) || $_FILES['PODPhoto']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'PODPhoto file is required']);
    mysqli_close($con);
    exit();
}

$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
// mime_content_type() requires fileinfo extension — fall back to browser-reported type
if (function_exists('mime_content_type')) {
    $file_type = mime_content_type($_FILES['PODPhoto']['tmp_name']);
} else {
    $file_type = $_FILES['PODPhoto']['type'];
}
if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type: ' . $file_type]);
    mysqli_close($con);
    exit();
}

// Max 10MB — same as abra_logistics
if ($_FILES['PODPhoto']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File exceeds 10MB limit']);
    mysqli_close($con);
    exit();
}

// ── Save file — uploads/ is at webroot, two levels up from this file ────
$uploads_dir = dirname(dirname(__DIR__)) . '/uploads/pickup-photos/';
if (!file_exists($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create uploads directory']);
        mysqli_close($con);
        exit();
    }
}

if (!is_writable($uploads_dir)) {
    echo json_encode(['status' => 'error', 'message' => 'Uploads directory is not writable']);
    mysqli_close($con);
    exit();
}

$file_ext  = pathinfo($_FILES['PODPhoto']['name'], PATHINFO_EXTENSION);
if (empty($file_ext)) $file_ext = 'jpg';
$filename  = $tracking . '_' . date('YmdHis') . '.' . $file_ext;
$file_path = $uploads_dir . $filename;

if (!move_uploaded_file($_FILES['PODPhoto']['tmp_name'], $file_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save POD photo file']);
    mysqli_close($con);
    exit();
}

// ── Store file path — avoid base64 DB storage which can exceed column limits ──
$relative_path = 'uploads/pickup-photos/' . $filename;

// Full public URL — use server's own domain so the URL is always correct
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host_name = $_SERVER['HTTP_HOST'] ?? 'crm.abra-logistic.com';
$full_url  = $scheme . '://' . $host_name . '/' . $relative_path;

// ── Sanitize inputs ───────────────────────────────────────────────────────
$s_tracking     = $con->real_escape_string($tracking);
$s_driver_id    = $con->real_escape_string($delivery_driver);
$s_receiver_n   = $con->real_escape_string($receiver_name);
$s_receiver_p   = $con->real_escape_string($receiver_phone);
$s_barcode      = !empty($scanned_barcode) ? $con->real_escape_string($scanned_barcode) : null;
$s_relative     = $con->real_escape_string($relative_path);
$s_full_url     = $con->real_escape_string($full_url);

// ── UPDATE courier — exact same SQL as abra_logistics UpdateConsignmentPODAsync ──
// Sets status = 'Delivered', works on 'Out for Delivery' or already 'Delivered'
// Build SET clause — only include optional columns if they exist
$set_extra = '';
$chk_ds = $con->query("SHOW COLUMNS FROM courier LIKE 'detailed_status'");
if ($chk_ds && $chk_ds->num_rows > 0) $set_extra .= ", detailed_status = 'Delivered'";
$chk_lu = $con->query("SHOW COLUMNS FROM courier LIKE 'last_updated'");
if ($chk_lu && $chk_lu->num_rows > 0) $set_extra .= ", last_updated = current_timestamp()";

$sql_update = "UPDATE courier
               SET status = 'Delivered' $set_extra
               WHERE (tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking')
               AND status NOT IN ('Delivered')";

$con->query($sql_update);
$affected = $con->affected_rows;

// Mark fleet_assignment as completed — join via courier to find correct al_number
// (fleet_assignments.al_number = courier.assigned_vehicle, NOT courier.tracking)
$sql_fleet = "UPDATE fleet_assignments f
              INNER JOIN courier c ON TRIM(c.assigned_vehicle) = TRIM(f.al_number)
              SET f.status = 'completed', f.actual_completion_date = NOW(), f.updated_at = NOW()
              WHERE (c.tracking = '$s_tracking' OR c.assigned_vehicle = '$s_tracking')
              AND f.status IN ('active', 'pending')";
$con->query($sql_fleet);

if (!empty($s_driver_id) && $s_driver_id !== 'NULL') {
    $lat_val = (!empty($latitude) && is_numeric($latitude)) ? floatval($latitude) : 'NULL';
    $lon_val = (!empty($longitude) && is_numeric($longitude)) ? floatval($longitude) : 'NULL';

    $sql_milestone = "INSERT INTO tsp_milestones 
                      (delivery_id, tracking, trStatus, trLatitude, trLongitude, trPODimage)
                      VALUES ('$s_driver_id', '$s_tracking', 'POD', $lat_val, $lon_val, '$s_full_url')";
    $con->query($sql_milestone);
}

// ── Write POD columns directly on courier (same as abra_logistics) ──
// This is what delivery-pod.php dashboard reads
$lat_d = (!empty($latitude)  && is_numeric($latitude))  ? floatval($latitude)  : 'NULL';
$lon_d = (!empty($longitude) && is_numeric($longitude)) ? floatval($longitude) : 'NULL';
$barcode_sql = !empty($s_barcode) ? "'$s_barcode'" : 'NULL';
$con->query("UPDATE royaldxd_abra_crm.courier
             SET delivery_pod_image     = '$s_full_url',
                 delivery_pod_timestamp = NOW(),
                 delivery_latitude      = $lat_d,
                 delivery_longitude     = $lon_d,
                 delivery_driver_id     = '$s_driver_id',
                 receiver_name_pod      = '$s_receiver_n',
                 receiver_phone_pod     = '$s_receiver_p',
                 scanned_barcode        = $barcode_sql,
                 rev_name               = '$s_receiver_n',
                 r_phone                = '$s_receiver_p'
             WHERE (tracking = '$s_tracking' OR assigned_vehicle = '$s_tracking')");

mysqli_close($con);

echo json_encode([
    'status'    => 'success',
    'message'   => 'Proof of delivery submitted successfully',
    'photo_url' => $full_url,
    'photo_relative' => $relative_path,
    'pod_type'  => 'delivery',
]);
?>
