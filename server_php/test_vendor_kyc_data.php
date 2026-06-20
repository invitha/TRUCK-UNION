<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'vendor_kyc'");
    $table_exists = $stmt->rowCount() > 0;
    
    // Count records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vendor_kyc");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get all records
    $stmt = $pdo->query("SELECT * FROM vendor_kyc ORDER BY created_at DESC LIMIT 5");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'table_exists' => $table_exists,
        'total_records' => $count,
        'recent_records' => $records
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
