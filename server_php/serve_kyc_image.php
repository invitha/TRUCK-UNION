<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$uid = $_GET['uid'] ?? '';
$file = $_GET['file'] ?? '';

if (empty($uid) || empty($file)) {
    http_response_code(400);
    die('Missing parameters');
}

$file = basename($file);
$uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $uid);

// Check both vendor and driver KYC directories
$vendor_upload_dir = '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents';
$driver_upload_dir = '/home/royaldxd/crm.abra-logistic.com/uploads/driver_kyc_documents';

// Try vendor KYC path first (with UID folder)
$vendor_file_path = $vendor_upload_dir . '/' . $uid . '/' . $file;

// Try driver KYC path (direct file)
$driver_file_path = $driver_upload_dir . '/' . $file;

$file_path = '';
if (file_exists($vendor_file_path)) {
    $file_path = $vendor_file_path;
} elseif (file_exists($driver_file_path)) {
    $file_path = $driver_file_path;
}

if (!$file_path || !file_exists($file_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'webp' => 'image/webp'
];

header('Content-Type: ' . ($content_types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600');
readfile($file_path);
