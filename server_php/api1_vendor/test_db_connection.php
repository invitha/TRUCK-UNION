<?php
/**
 * Test Database Connection
 * This will show if the database connection is working
 */

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n\n";

try {
    echo "Step 1: Including database files...\n";
    require_once('../../dashboard/database.php');
    echo "✓ database.php included\n";
    
    require_once('../../dashboard/library.php');
    echo "✓ library.php included\n";
    
    require_once('../../dashboard/funciones.php');
    echo "✓ funciones.php included\n\n";
    
    echo "Step 2: Connecting to database...\n";
    $con = conexion();
    
    if (!$con) {
        echo "✗ Connection failed: " . mysqli_connect_error() . "\n";
        exit();
    }
    
    echo "✓ Database connected successfully!\n\n";
    
    echo "Step 3: Testing vendor_kyc table...\n";
    $result = mysqli_query($con, "SHOW TABLES LIKE 'vendor_kyc'");
    
    if (mysqli_num_rows($result) > 0) {
        echo "✓ vendor_kyc table exists\n\n";
        
        // Get table structure
        echo "Step 4: Checking table structure...\n";
        $structure = mysqli_query($con, "DESCRIBE vendor_kyc");
        while ($row = mysqli_fetch_assoc($structure)) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
        
        echo "\n✓ Everything looks good!\n";
    } else {
        echo "✗ vendor_kyc table NOT found\n";
        echo "Please run create_vendor_kyc_table.sql in phpMyAdmin\n";
    }
    
    mysqli_close($con);
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
