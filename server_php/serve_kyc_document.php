<?php
// Serve KYC documents securely - Updated to handle both vendor and driver documents
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$file = $_GET['file'] ?? '';
$uid = $_GET['uid'] ?? '';

if (empty($file)) {
    http_response_code(400);
    echo 'Missing file parameter';
    exit();
}

// Sanitize file name
$file = basename($file);

// Define possible paths for documents
$possible_paths = [];

// If UID is provided, try vendor KYC path structure (with UID folder)
if (!empty($uid)) {
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $uid);
    $possible_paths[] = '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/' . $uid . '/' . $file;
}

// Try driver KYC documents (direct file)
$possible_paths[] = '/home/royaldxd/crm.abra-logistic.com/uploads/driver_kyc_documents/' . $file;

// Try alternative driver KYC path
$possible_paths[] = '/home/royaldxd/crm.abra-logistic.com/api1/driver_kyc_documents/' . $file;

// Try local uploads folder (relative path)
$possible_paths[] = __DIR__ . '/uploads/vendor_kyc_documents/' . ($uid ? $uid . '/' : '') . $file;
$possible_paths[] = __DIR__ . '/uploads/driver_kyc_documents/' . $file;

// Find existing file
$file_path = '';
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $file_path = $path;
        break;
    }
}

if (!$file_path) {
    http_response_code(404);
    echo 'File not found: ' . htmlspecialchars($file);
    exit();
}

// Get file info
$file_info = pathinfo($file_path);
$extension = strtolower($file_info['extension'] ?? '');

// Set content type based on extension
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$content_type = $content_types[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// For images, allow inline display; for documents, force download
if (strpos($content_type, 'image/') === 0) {
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
}

// Output file
readfile($file_path);
exit();
?>
