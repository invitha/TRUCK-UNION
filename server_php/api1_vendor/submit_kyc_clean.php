<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['firebase_uid'])) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid data']));
}

$firebase_uid = $data['firebase_uid'];
$account_type = $data['account_type'] ?? 'individual';
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$aadhaar_number = $data['aadhaar_number'] ?? '';
$pan_number = $data['pan_number'] ?? '';
$company_name = $data['company_name'] ?? null;
$gst_number = $data['gst_number'] ?? null;
$address = $data['address'] ?? null;
$bank_account_name = $data['bank_account_name'] ?? '';
$bank_account_number = $data['bank_account_number'] ?? '';
$ifsc_code = $data['ifsc_code'] ?? '';
$aadhaar_doc = $data['aadhaar_doc'] ?? null;
$pan_doc = $data['pan_doc'] ?? null;
$photo_doc = $data['photo_doc'] ?? null;
$gst_doc = $data['gst_doc'] ?? null;
$address_doc = $data['address_doc'] ?? null;
$bank_account_photo = $data['bank_account_photo'] ?? null;

$stmt = $con->prepare("SELECT id FROM vendor_kyc WHERE firebase_uid = ?");
$stmt->bind_param('s', $firebase_uid);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if ($existing) {
    $stmt = $con->prepare("UPDATE vendor_kyc SET account_type=?, name=?, email=?, phone=?, aadhaar_number=?, pan_number=?, company_name=?, gst_number=?, address=?, bank_account_name=?, bank_account_number=?, ifsc_code=?, aadhaar_doc=?, pan_doc=?, photo_doc=?, gst_doc=?, address_doc=?, bank_account_photo=?, kyc_status='submitted', updated_at=NOW() WHERE firebase_uid=?");
    $stmt->bind_param('sssssssssssssssssss', $account_type, $name, $email, $phone, $aadhaar_number, $pan_number, $company_name, $gst_number, $address, $bank_account_name, $bank_account_number, $ifsc_code, $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo, $firebase_uid);
} else {
    $stmt = $con->prepare("INSERT INTO vendor_kyc (firebase_uid, account_type, name, email, phone, aadhaar_number, pan_number, company_name, gst_number, address, bank_account_name, bank_account_number, ifsc_code, aadhaar_doc, pan_doc, photo_doc, gst_doc, address_doc, bank_account_photo, kyc_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW(), NOW())");
    $stmt->bind_param('ssssssssssssssssss', $firebase_uid, $account_type, $name, $email, $phone, $aadhaar_number, $pan_number, $company_name, $gst_number, $address, $bank_account_name, $bank_account_number, $ifsc_code, $aadhaar_doc, $pan_doc, $photo_doc, $gst_doc, $address_doc, $bank_account_photo);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'KYC submitted successfully', 'kyc_status' => 'submitted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $stmt->error]);
}

$stmt->close();
$con->close();
