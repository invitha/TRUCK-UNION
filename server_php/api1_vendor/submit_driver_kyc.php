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
    // Database connection (Hardcoded to prevent 500 errors)
    $host = 'localhost';
    $dbname = 'royaldxd_abra_crm';
    $username = 'royaldxd_user';
    $password = 'meg_layout312';
    
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        throw new Exception('Database connection failed: ' . $con->connect_error);
    }
    
    $con->set_charset('utf8mb4');
    
    // Get POST data
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    if (!$data) {
        $data = $_REQUEST;
    }
    if (empty($data)) {
        $json_err = json_last_error_msg();
        throw new Exception('Invalid request data. JSON Err: ' . $json_err . ' RAW: ' . $raw_input . ' POST: ' . json_encode($_POST));
    }
    
    // Extract required fields
    $firebase_uid = $data['firebase_uid'] ?? '';
    $driver_name = $data['driver_name'] ?? '';
    $driver_mobile = $data['driver_mobile'] ?? '';
    $driver_email = $data['driver_email'] ?? '';
    $aadhar_number = $data['aadhar_number'] ?? '';
    $pan_number = $data['pan_number'] ?? '';
    $license_number = $data['license_number'] ?? '';
    $address = $data['address'] ?? '';
    $city = $data['city'] ?? '';
    $state = $data['state'] ?? '';
    $pincode = $data['pincode'] ?? '';
    $vehicle_number = $data['vehicle_number'] ?? ''; // <--- Added back vehicle_number
    
    // Validate required fields
    if (empty($firebase_uid) || empty($driver_name) || empty($driver_mobile)) {
        throw new Exception('All required fields must be filled (name and mobile)');
    }
    
    // Auto-fetch vehicle number from vehicles table if not provided
    if (empty($vehicle_number)) {
        // Robust match: compare the last 10 digits of the phone number to ignore +91 or leading zeros
        $v_stmt = $con->prepare("SELECT vehicle_number FROM vehicles WHERE RIGHT(driver_phone, 10) = RIGHT(?, 10) ORDER BY id DESC LIMIT 1");
        $v_stmt->bind_param('s', $driver_mobile);
        $v_stmt->execute();
        $v_result = $v_stmt->get_result();
        if ($v_row = $v_result->fetch_assoc()) {
            $vehicle_number = $v_row['vehicle_number'];
        }
        $v_stmt->close();
    }
    

    
    // Check if KYC already exists
    $check_stmt = $con->prepare("SELECT id, kyc_status FROM driver_kyc WHERE firebase_uid = ?");
    $check_stmt->bind_param('s', $firebase_uid);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $update_stmt = $con->prepare("UPDATE driver_kyc SET 
            driver_name = ?, driver_mobile = ?, driver_email = ?, 
            aadhar_number = ?, pan_number = ?, license_number = ?,
            address = ?, city = ?, state = ?, pincode = ?, vehicle_number = ?,
            kyc_status = 'pending', submitted_at = NOW(), updated_at = NOW()
            WHERE firebase_uid = ?");
            
        $update_stmt->bind_param('ssssssssssss', 
            $driver_name, $driver_mobile, $driver_email,
            $aadhar_number, $pan_number, $license_number,
            $address, $city, $state, $pincode, $vehicle_number, $firebase_uid);
            
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update KYC: ' . $con->error);
        }
    } else {
        // Insert new record
        $insert_stmt = $con->prepare("INSERT INTO driver_kyc 
            (firebase_uid, driver_name, driver_mobile, driver_email, aadhar_number, pan_number, license_number, 
             address, city, state, pincode, vehicle_number, kyc_status, submitted_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
        $insert_stmt->bind_param('ssssssssssss', 
            $firebase_uid, $driver_name, $driver_mobile, $driver_email,
            $aadhar_number, $pan_number, $license_number,
            $address, $city, $state, $pincode, $vehicle_number);
            
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to insert KYC: ' . $con->error);
        }
    }
    
    // Insert into notifications
    $notification_title = 'KYC Submitted Successfully';
    $notification_message = 'Your KYC documents have been submitted. Our team will review and verify them shortly.';
    $notif_query = "INSERT INTO notifications (firebase_uid, title, message, type, created_at) VALUES (?, ?, ?, 'kyc_update', NOW())";
    $notif_stmt = $con->prepare($notif_query);
    if ($notif_stmt) {
        $notif_stmt->bind_param('sss', $firebase_uid, $notification_title, $notification_message);
        $notif_stmt->execute();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Driver KYC submitted successfully'
    ]);
    
} catch (Exception $e) {
    // Log exactly what happened
    file_put_contents('kyc_debug_log.txt', date('Y-m-d H:i:s') . ' - ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    
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
