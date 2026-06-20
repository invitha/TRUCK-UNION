<?php
/**
 * Get All Vendor KYC Submissions
 * Returns all KYC data separated by Individual and Business vendors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
            account_type ASC,
            CASE kyc_status
                WHEN 'submitted' THEN 1
                WHEN 'verified' THEN 2
                WHEN 'rejected' THEN 3
            END,
            created_at DESC
    ");
    
    $stmt->execute();
    $all_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate by account type
    $individual_vendors = [];
    $business_vendors = [];
    
    foreach ($all_kyc as $kyc) {
        if ($kyc['account_type'] === 'individual') {
            $individual_vendors[] = $kyc;
        } else if ($kyc['account_type'] === 'business') {
            $business_vendors[] = $kyc;
        }
    }
    
    // Count by status
    $stats = [
        'total' => count($all_kyc),
        'individual_count' => count($individual_vendors),
        'business_count' => count($business_vendors),
        'submitted' => 0,
        'verified' => 0,
        'rejected' => 0
    ];
    
    foreach ($all_kyc as $kyc) {
        if ($kyc['kyc_status'] === 'submitted') $stats['submitted']++;
        if ($kyc['kyc_status'] === 'verified') $stats['verified']++;
        if ($kyc['kyc_status'] === 'rejected') $stats['rejected']++;
    }
    
    echo json_encode([
        'status' => 'success',
        'kyc_submissions' => $all_kyc,
        'individual_vendors' => $individual_vendors,
        'business_vendors' => $business_vendors,
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
