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
ini_set('display_errors', 1);

try {
    // Database connection (Hardcoded)
    $host = 'localhost';
    $dbname = 'royaldxd_abra_crm';
    $username = 'royaldxd_user';
    $password = 'meg_layout312';
    
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        throw new Exception('Database connection failed: ' . $con->connect_error);
    }
    
    $con->set_charset('utf8mb4');

    $firebase_uid = isset($_POST['firebase_uid']) ? trim($_POST['firebase_uid']) : '';
    $document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';
    
    if (empty($firebase_uid) || empty($document_type)) {
        throw new Exception('Firebase UID and document type are required');
    }
    
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!in_array($_FILES['document']['type'], $allowed_types)) {
        throw new Exception('Only JPG, PNG, and PDF files are allowed');
    }
    
    $upload_dir = '../driver_kyc_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
    $filename = $firebase_uid . '_' . $document_type . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save file');
    }
    
    $column_name = '';
    switch ($document_type) {
        case 'aadhar_front': $column_name = 'aadhar_front_image'; break;
        case 'aadhar_back': $column_name = 'aadhar_back_image'; break;
        case 'pan': $column_name = 'pan_image'; break;
        case 'license_front': $column_name = 'license_front_image'; break;
        case 'license_back': $column_name = 'license_back_image'; break;
        case 'rc_front': $column_name = 'rc_front_image'; break;
        case 'rc_back': $column_name = 'rc_back_image'; break;
        case 'insurance': $column_name = 'insurance_image'; break;
        case 'fitness': $column_name = 'fitness_image'; break;
        case 'puc': $column_name = 'puc_image'; break;
        case 'vehicle_front': $column_name = 'vehicle_photo_front'; break;
        case 'vehicle_side': $column_name = 'vehicle_photo_side'; break;
        default:
            unlink($upload_path);
            throw new Exception('Invalid document type');
    }
    
    $query = "UPDATE driver_kyc SET $column_name = ?, updated_at = NOW() WHERE firebase_uid = ?";
    $stmt = mysqli_prepare($con, $query);
    
    if (!$stmt) {
        unlink($upload_path);
        throw new Exception('DB Error: ' . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $filename, $firebase_uid);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Document uploaded successfully', 'filename' => $filename]);
    } else {
        unlink($upload_path);
        throw new Exception('Failed to update DB');
    }
    mysqli_close($con);

} catch (Exception $e) {
    http_response_code(200); // Return 200 so Flutter Dio parses the custom error message instead of throwing
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}
?>
