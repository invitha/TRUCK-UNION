<?php
// CORS headers - MUST be first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $firebase_uid = $_POST['firebase_uid'] ?? '';
    
    if (empty($firebase_uid)) {
        throw new Exception('Firebase UID is required');
    }

    // Use __DIR__ for relative path from api1/vendor/
    $upload_base_dir = __DIR__ . '/../../uploads/vendor_kyc_documents';
    if (!file_exists($upload_base_dir)) {
        mkdir($upload_base_dir, 0755, true);
    }

    $user_upload_dir = $upload_base_dir . '/' . $firebase_uid;
    if (!file_exists($user_upload_dir)) {
        mkdir($user_upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    $max_file_size = 5 * 1024 * 1024;

    $uploaded_files = [];
    $errors = [];

    foreach ($_FILES as $field_name => $file) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "$field_name: Upload error";
            continue;
        }
        
        $file_type = mime_content_type($file['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "$field_name: Invalid file type";
            continue;
        }
        
        if ($file['size'] > $max_file_size) {
            $errors[] = "$field_name: File too large";
            continue;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $field_name . '_' . time() . '_' . uniqid() . '.' . $extension;
        $destination = $user_upload_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $relative_path = 'uploads/vendor_kyc_documents/' . $firebase_uid . '/' . $filename;
            $uploaded_files[$field_name] = $relative_path;
        } else {
            $errors[] = "$field_name: Failed to upload";
        }
    }

    if (empty($uploaded_files) && !empty($errors)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No files uploaded successfully',
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Files uploaded successfully',
            'uploaded_files' => $uploaded_files,
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
