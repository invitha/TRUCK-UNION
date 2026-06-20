<?php
// SIMPLE API FIX - Replace your submit_driver_kyc.php with this
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Database connection
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
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
    
    // Validate required fields
    if (empty($firebase_uid) || empty($driver_name) || empty($driver_mobile) || 
        empty($aadhar_number) || empty($pan_number) || empty($license_number)) {
        throw new Exception('Missing required fields');
    }
    
    // Check if table exists, create if not
    $table_check = $con->query("SHOW TABLES LIKE 'driver_kyc'");
    if ($table_check->num_rows == 0) {
        // Create table
        $create_table = "CREATE TABLE `driver_kyc` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            firebase_uid VARCHAR(255) NOT NULL UNIQUE,
            driver_name VARCHAR(255) NOT NULL,
            driver_mobile VARCHAR(20) NOT NULL,
            driver_email VARCHAR(255) NULL,
            aadhar_number VARCHAR(12) NOT NULL,
            aadhar_front_image VARCHAR(255) NULL,
            aadhar_back_image VARCHAR(255) NULL,
            pan_number VARCHAR(10) NOT NULL,
            pan_image VARCHAR(255) NULL,
            license_number VARCHAR(50) NOT NULL,
            license_front_image VARCHAR(255) NULL,
            license_back_image VARCHAR(255) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            state VARCHAR(100) NULL,
            pincode VARCHAR(10) NULL,
            kyc_status ENUM('pending', 'submitted', 'under_review', 'verified', 'rejected', 'revoked') DEFAULT 'submitted',
            rejection_reason TEXT NULL,
            admin_notes TEXT NULL,
            vehicle_number VARCHAR(50) NULL,
            rc_front_image VARCHAR(255) NULL,
            rc_back_image VARCHAR(255) NULL,
            insurance_image VARCHAR(255) NULL,
            fitness_image VARCHAR(255) NULL,
            puc_image VARCHAR(255) NULL,
            vehicle_photo_front VARCHAR(255) NULL,
            vehicle_photo_side VARCHAR(255) NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$con->query($create_table)) {
            throw new Exception('Failed to create driver_kyc table: ' . $con->error);
        }
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
            address = ?, city = ?, state = ?, pincode = ?,
            kyc_status = 'submitted', submitted_at = NOW(), updated_at = NOW()
            WHERE firebase_uid = ?");
            
        $update_stmt->bind_param('sssssssssss', 
            $driver_name, $driver_mobile, $driver_email,
            $aadhar_number, $pan_number, $license_number,
            $address, $city, $state, $pincode, $firebase_uid);
            
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update KYC: ' . $con->error);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Driver KYC updated successfully'
        ]);
        
    } else {
        // Insert new record
        $insert_stmt = $con->prepare("INSERT INTO driver_kyc 
            (firebase_uid, driver_name, driver_mobile, driver_email, aadhar_number, pan_number, license_number, 
             address, city, state, pincode, kyc_status, submitted_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())");
            
        $insert_stmt->bind_param('sssssssssss', 
            $firebase_uid, $driver_name, $driver_mobile, $driver_email,
            $aadhar_number, $pan_number, $license_number,
            $address, $city, $state, $pincode);
            
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to insert KYC: ' . $con->error);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Driver KYC submitted successfully'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
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