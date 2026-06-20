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

require_once '../db_config.php';

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

// Check if file was uploaded
if (!isset($_FILES['pod_image']) || $_FILES['pod_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    mysqli_close($con);
    exit();
}

// Get form data
$al_number = isset($_POST['al_number']) ? trim($_POST['al_number']) : '';
$vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
$pod_type = isset($_POST['pod_type']) ? trim($_POST['pod_type']) : ''; // 'pickup' or 'delivery'

if (empty($al_number) || empty($vehicle_id) || empty($pod_type)) {
    echo json_encode(['status' => 'error', 'message' => 'AL Number, Vehicle ID, and POD type are required']);
    mysqli_close($con);
    exit();
}

// Verify order belongs to this vehicle
$check_stmt = mysqli_prepare($con, "SELECT id FROM customer_orders WHERE al_number = ? AND vehicle_id = ?");
mysqli_stmt_bind_param($check_stmt, 'si', $al_number, $vehicle_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (!mysqli_fetch_assoc($check_result)) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found or unauthorized']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($con);
    exit();
}
mysqli_stmt_close($check_stmt);

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
$file_type = $_FILES['pod_image']['type'];

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Only JPG, JPEG, and PNG files are allowed']);
    mysqli_close($con);
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = '../pod_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($_FILES['pod_image']['name'], PATHINFO_EXTENSION);
$filename = $al_number . '_' . $pod_type . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($_FILES['pod_image']['tmp_name'], $upload_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    mysqli_close($con);
    exit();
}

// Update database
$column_name = ($pod_type === 'pickup') ? 'pickup_pod_image' : 'delivery_pod_image';
$query = "UPDATE customer_orders SET $column_name = ? WHERE al_number = ? AND vehicle_id = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'ssi', $filename, $al_number, $vehicle_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'POD uploaded successfully',
        'filename' => $filename,
        'pod_type' => $pod_type
    ]);
} else {
    // Delete uploaded file if database update fails
    unlink($upload_path);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update database: ' . mysqli_error($con)]);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
