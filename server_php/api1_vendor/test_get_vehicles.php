<?php
/**
 * Test Get Vehicles - Diagnostic Version
 */

// CORS headers FIRST - before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed',
            'error' => $con->connect_error
        ]);
        exit();
    }
    
    $con->set_charset('utf8mb4');
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
    exit();
}

// Get firebase_uid from either GET or POST
$firebase_uid = '';
if (isset($_GET['firebase_uid'])) {
    $firebase_uid = trim($_GET['firebase_uid']);
} elseif (isset($_POST['firebase_uid'])) {
    $firebase_uid = trim($_POST['firebase_uid']);
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['firebase_uid'])) {
        $firebase_uid = trim($input['firebase_uid']);
    }
}

if (empty($firebase_uid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Firebase UID is required',
        'debug' => [
            'GET' => $_GET,
            'POST' => $_POST,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
    mysqli_close($con);
    exit();
}

// Get all vehicles for this vendor
$stmt = mysqli_prepare($con, "
    SELECT * FROM vehicles 
    WHERE firebase_uid = ? 
    ORDER BY created_at DESC
");

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Query preparation failed',
        'error' => mysqli_error($con)
    ]);
    mysqli_close($con);
    exit();
}

mysqli_stmt_bind_param($stmt, 's', $firebase_uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vehicles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vehicles[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'vehicles' => $vehicles,
    'total' => count($vehicles),
    'firebase_uid' => $firebase_uid,
    'debug' => [
        'cors_headers_sent' => true,
        'database' => $dbname
    ]
], JSON_PRETTY_PRINT);

mysqli_close($con);
