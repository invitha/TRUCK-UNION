<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db_config.php';

try {
    // Check if table exists and show its structure
    $stmt = $conn->query("DESCRIBE vehicles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count existing records
    $countStmt = $conn->query("SELECT COUNT(*) as total FROM vehicles");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Vehicles table exists',
        'structure' => $columns,
        'record_count' => $count['total']
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
