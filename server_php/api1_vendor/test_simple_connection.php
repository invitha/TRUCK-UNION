<?php
/**
 * Simple Database Connection Test
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once('db_config.php');
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'database' => $db_name,
        'host' => $db_host
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Connection failed: ' . $e->getMessage()
    ]);
}
