<?php
/**
 * Diagnostic script to check fleet_assignments table status
 * and provide fix instructions
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$response = [
    'status' => 'checking',
    'database' => $db_name,
    'checks' => []
];

// Check 1: Does the table exist?
$tableCheck = $conn->query("SHOW TABLES LIKE 'fleet_assignments'");
$tableExists = $tableCheck && $tableCheck->num_rows > 0;

$response['checks']['table_exists'] = [
    'status' => $tableExists ? 'pass' : 'fail',
    'message' => $tableExists ? 'Table exists' : 'Table does NOT exist'
];

if ($tableExists) {
    // Check 2: Get current table structure
    $columnsResult = $conn->query("DESCRIBE fleet_assignments");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $response['checks']['current_columns'] = [
        'status' => 'info',
        'columns' => $columns
    ];
    
    // Check 3: Check for payment columns
    $paymentColumns = [
        'payment_status',
        'payment_amount',
        'advance_amount',
        'remaining_amount',
        'payment_date',
        'payment_notes'
    ];
    
    $missingColumns = array_diff($paymentColumns, $columns);
    
    $response['checks']['payment_columns'] = [
        'status' => empty($missingColumns) ? 'pass' : 'fail',
        'message' => empty($missingColumns) 
            ? 'All payment columns exist' 
            : 'Missing payment columns: ' . implode(', ', $missingColumns),
        'missing' => $missingColumns
    ];
    
    // Check 4: Count records
    $countResult = $conn->query("SELECT COUNT(*) as total FROM fleet_assignments");
    $count = $countResult->fetch_assoc()['total'];
    
    $response['checks']['record_count'] = [
        'status' => 'info',
        'count' => $count,
        'message' => "Table has $count records"
    ];
    
} else {
    $response['checks']['solution'] = [
        'status' => 'action_required',
        'message' => 'Table needs to be created',
        'instructions' => [
            '1. Run the SQL file: create_fleet_assignments_with_payment.sql',
            '2. Or use the create_table endpoint below'
        ]
    ];
}

// Check 5: Verify vehicles table exists (foreign key dependency)
$vehiclesCheck = $conn->query("SHOW TABLES LIKE 'vehicles'");
$vehiclesExists = $vehiclesCheck && $vehiclesCheck->num_rows > 0;

$response['checks']['vehicles_table'] = [
    'status' => $vehiclesExists ? 'pass' : 'fail',
    'message' => $vehiclesExists 
        ? 'Vehicles table exists (required for foreign key)' 
        : 'Vehicles table does NOT exist (required before creating fleet_assignments)'
];

$response['status'] = 'complete';
$response['summary'] = $tableExists 
    ? 'Table exists - check for missing columns above'
    : 'Table does not exist - needs to be created';

echo json_encode($response, JSON_PRETTY_PRINT);

$conn->close();
?>
