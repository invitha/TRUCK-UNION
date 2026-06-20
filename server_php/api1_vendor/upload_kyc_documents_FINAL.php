<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);

if ($con->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

$con->set_charset('utf8mb4');

// Include notification helper
require_once('create_notification.php');

// Get form data
$firebase_uid = $_POST['firebase_uid'] ?? '';
$account_type = $_POST['account_type'] ?? 'individual';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$aadhaar_number = $_POST['aadhaar_number'] ?? '';
$pan_number = $_POST['pan_number'] ?? '';
$company_name = $_POST['company_name'] ?? null;
$gst_number = $_POST['gst_number'] ?? null;
$address = $_POST['address'] ?? null;
$bank_account_name = $_POST['bank_account_name'] ?? '';
$bank_account_number = $_POST['bank_account_number'] ?? '';
$ifsc_code = $_POST['ifsc_code'] ?? '';

if (empty($firebase_uid)) {
    die(json_encode(['status' => 'error', 'message' => 'Firebase UID required']));
}

// Handle file uploads (mock for now)
$aadhaar_doc = null;
$pan_doc = null;
$photo_doc = null;
$gst_doc = null;
$address_doc = null;
$bank_account_photo = null;

if (isset($_FILES['aadhaar'])) $aadhaar_doc = 'uploads/vendor_kyc/' . $firebase_uid . '/aadhaar_' . time() . '.jpg';
if (isset($_FILES['pan'])) $pan_doc = 'uploads/vendor_kyc/' . $firebase_uid . '/pan_' . time() . '.jpg';
if (isset($_FILES['photo'])) $photo_doc = 'uploads/vendor_kyc/' . $firebase_uid . '/photo_' . time() . '.jpg';
if (isset($_FILES['gst'])) $gst_doc = 'uploads/vendor_kyc/' . $firebase_uid . '/gst_' . time() . '.jpg';
if (isset($_FILES['address_proof'])) $address_doc = 'uploads/vendor_kyc/' . $firebase_uid . '/address_' . time() . '.jpg';
if (isset($_FILES['bank_account_photo'])) $bank_account_photo = 'uploads/vendor_kyc/' . $firebase_uid . '/bank_' . time() . '.jpg';

// Check if exists
$stmt = $con->prepare("SELECT id FROM vendor_kyc WHERE firebase_uid = ?");
$stmt->bind_param('s', $firebase_uid);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if ($existing) {
    // Update
    $stmt = $con->prepare("UPDATE vendor_kyc SET account_type=?, name=?, email=?, phone=?, aadhaar_number=?, pan_number=?, company_name=?, gst_number=?, address=?, bank_account_name=?, bank_account_number=?, ifsc_code=?, aadhaar_doc=?, pan_doc=?, photo_doc=?, gst_doc=?, address_doc=?, bank_account_photo=?, kyc_status='submitted', updated_at=NOW() WHERE firebase_uid=?");
    $stmt->bind_param('sssssssssssssssssss', $account_type, $name, $email, $phone, $aadhaar_number, $pan_number, $company_name, $gst_number, $address, $bank_account_name, $bank_account_number, $ifsc_code, $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo, $firebase_uid);
} else {
    // Insert
    $stmt = $con->prepare("INSERT INTO vendor_kyc (firebase_uid, account_type, name, email, phone, aadhaar_number, pan_number, company_name, gst_number, address, bank_account_name, bank_account_number, ifsc_code, aadhaar_doc, pan_doc, photo_doc, gst_doc, address_doc, bank_account_photo, kyc_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW(), NOW())");
    $stmt->bind_param('sssssssssssssssssss', $firebase_uid, $account_type, $name, $email, $phone, $aadhaar_number, $pan_number, $company_name, $gst_number, $address, $bank_account_name, $bank_account_number, $ifsc_code, $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo);
}

if ($stmt->execute()) {
    // Create notification for KYC submission
    createNotification(
        $con,
        $firebase_uid,
        'kyc_submitted',
        '📋 KYC Submitted Successfully',
        'Your KYC documents have been submitted and are under review. Verification usually takes 24-48 hours.'
    );
    
    echo json_encode([
        'status' => 'success',
        'message' => 'KYC submitted successfully',
        'uploaded_files' => [
            'aadhaar' => $aadhaar_doc,
            'pan' => $pan_doc,
            'photo' => $photo_doc,
            'gst' => $gst_doc,
            'address_proof' => $address_doc,
            'bank_account_photo' => $bank_account_photo
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $stmt->error]);
}

$stmt->close();
$con->close();
