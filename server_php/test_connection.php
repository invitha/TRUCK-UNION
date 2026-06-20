<?php
/**
 * Database Connection Test
 * 
 * Upload this file to your server and access it in browser to test database connection
 * Example: https://crm.abra-logistic.com/test_connection.php
 * 
 * DELETE THIS FILE after testing for security!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><style>
body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
.success { color: #059669; font-weight: bold; }
.error { color: #dc2626; font-weight: bold; }
.info { color: #0284c7; }
h1 { color: #1e293b; margin-top: 0; }
pre { background: #f8fafc; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style></head><body><div class='box'>";

echo "<h1>🔍 Database Connection Test</h1>";

// Test 1: Check if db_config.php exists
echo "<h3>Test 1: Check db_config.php</h3>";
if (file_exists('db_config.php')) {
    echo "<p class='success'>✓ db_config.php file exists</p>";
} else {
    echo "<p class='error'>✗ db_config.php file NOT found</p>";
    echo "<p>Please create db_config.php in the same directory as this file.</p>";
    exit();
}

// Test 2: Include db_config.php
echo "<h3>Test 2: Load Database Configuration</h3>";
try {
    require_once('db_config.php');
    echo "<p class='success'>✓ db_config.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading db_config.php: " . $e->getMessage() . "</p>";
    exit();
}

// Test 3: Check connection
echo "<h3>Test 3: Database Connection</h3>";
if (isset($conn) && $conn) {
    echo "<p class='success'>✓ Database connected successfully!</p>";
    echo "<p class='info'>Database: " . htmlspecialchars($db_name) . "</p>";
    echo "<p class='info'>Host: " . htmlspecialchars($db_host) . "</p>";
} else {
    echo "<p class='error'>✗ Database connection failed</p>";
    if (isset($conn)) {
        echo "<p class='error'>Error: " . $conn->connect_error . "</p>";
    }
    exit();
}

// Test 4: Check if vendor_kyc table exists
echo "<h3>Test 4: Check vendor_kyc Table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'vendor_kyc'");
if ($result && $result->num_rows > 0) {
    echo "<p class='success'>✓ vendor_kyc table exists</p>";
    
    // Get table structure
    $structure = $conn->query("DESCRIBE vendor_kyc");
    echo "<p class='info'>Table structure:</p>";
    echo "<pre>";
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
    
    // Count records
    $count_result = $conn->query("SELECT COUNT(*) as total FROM vendor_kyc");
    $count = $count_result->fetch_assoc()['total'];
    echo "<p class='info'>Total records: " . $count . "</p>";
    
} else {
    echo "<p class='error'>✗ vendor_kyc table NOT found</p>";
    echo "<p>Please run create_vendor_kyc_table.sql in phpMyAdmin to create the table.</p>";
}

// Test 5: Check notifications table (optional)
echo "<h3>Test 5: Check notifications Table (Optional)</h3>";
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result && $result->num_rows > 0) {
    echo "<p class='success'>✓ notifications table exists</p>";
} else {
    echo "<p class='error'>✗ notifications table NOT found</p>";
    echo "<p>This is optional but recommended for sending notifications to vendors.</p>";
}

// Summary
echo "<h3>📊 Summary</h3>";
echo "<p><strong>Status:</strong> ";
if (isset($conn) && $conn && $result && $result->num_rows > 0) {
    echo "<span class='success'>✓ All tests passed! You can now use vendor-verification.php</span>";
    echo "<p class='info'>Access the admin panel at: <a href='vendor-verification.php'>vendor-verification.php</a></p>";
} else {
    echo "<span class='error'>✗ Some tests failed. Please fix the issues above.</span>";
}
echo "</p>";

echo "<hr><p style='color: #64748b; font-size: 12px;'>⚠️ <strong>IMPORTANT:</strong> Delete this test_connection.php file after testing for security!</p>";

echo "</div></body></html>";

$conn->close();
?>
