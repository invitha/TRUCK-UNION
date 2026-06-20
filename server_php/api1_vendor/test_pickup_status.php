<?php
/**
 * Diagnostic test for pickup_status.php
 * Upload this to server and visit in browser to check what's failing
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$results = [];

// 1. Check PHP version
$results['php_version'] = PHP_VERSION;

// 2. Check DB connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    $results['db'] = 'FAILED: ' . $con->connect_error;
} else {
    $results['db'] = 'OK';
    $con->close();
}

// 3. Check uploads directory
$uploads_dir = dirname(__DIR__) . '/uploads/pickup-photos/';
$results['uploads_dir_path'] = $uploads_dir;
$results['uploads_parent_exists'] = file_exists(dirname(__DIR__) . '/uploads/') ? 'YES' : 'NO';
$results['uploads_dir_exists'] = file_exists($uploads_dir) ? 'YES' : 'NO';
$results['uploads_dir_writable'] = (file_exists($uploads_dir) && is_writable($uploads_dir)) ? 'YES' : 'NO';

// 4. Try to create uploads dir if missing
if (!file_exists($uploads_dir)) {
    $created = mkdir($uploads_dir, 0755, true);
    $results['mkdir_result'] = $created ? 'Created successfully' : 'FAILED to create';
    $results['mkdir_error'] = error_get_last();
} else {
    $results['mkdir_result'] = 'Already exists';
}

// 5. Check if tsp_milestones table exists
$con2 = new mysqli($host, $username, $password, $dbname);
if (!$con2->connect_error) {
    $r = $con2->query("SHOW TABLES LIKE 'tsp_milestones'");
    $results['tsp_milestones_table'] = ($r && $r->num_rows > 0) ? 'EXISTS' : 'NOT FOUND';
    
    if ($r && $r->num_rows > 0) {
        $cols = $con2->query("DESCRIBE tsp_milestones");
        $columns = [];
        while ($row = $cols->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $results['tsp_milestones_columns'] = $columns;
    }
    $con2->close();
}

echo json_encode($results, JSON_PRETTY_PRINT);
