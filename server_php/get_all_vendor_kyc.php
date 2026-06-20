<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all vendor KYC submissions
    $stmt = $pdo->prepare("
        SELECT 
            id,
            firebase_uid,
            account_type,
            name,
            email,
            phone,
            aadhaar_number,
            pan_number,
            company_name,
            gst_number,
            address,
            bank_account_name,
            bank_account_number,
            ifsc_code,
            aadhaar_doc,
            pan_doc,
            photo_doc,
            gst_doc,
            address_doc,
            bank_account_photo,
            kyc_status,
            rejection_reason,
            verified_at,
            created_at,
            updated_at
        FROM vendor_kyc
        ORDER BY 
            CASE kyc_status
                WHEN 'submitted' THEN 1
                WHEN 'verified' THEN 2
                WHEN 'rejected' THEN 3
            END,
            created_at DESC
    ");
    
    $stmt->execute();
    $kyc_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    foreach ($kyc_submissions as &$kyc) {
        // Document paths are already in the correct format
        // No need to parse JSON since they're individual columns
    }
    
    echo json_encode([
        'status' => 'success',
        'kyc_submissions' => $kyc_submissions,
        'total' => count($kyc_submissions)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
