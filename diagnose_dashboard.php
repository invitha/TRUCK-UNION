<?php
/**
 * Dashboard Diagnostic Script
 * Run this file to check what's wrong with dashboard.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Dashboard Diagnostic Report</h1>";
echo "<hr>";

// 1. Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: PHP 7.0 or higher<br>";
echo phpversion() >= 7.0 ? "✅ OK" : "❌ FAIL";
echo "<hr>";

// 2. Check database connection
echo "<h2>2. Database Connection</h2>";
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        echo "❌ Connection failed: " . $con->connect_error . "<br>";
    } else {
        echo "✅ Database connected successfully<br>";
        echo "Database: $dbname<br>";
        $con->set_charset('utf8mb4');
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
    exit;
}
echo "<hr>";

// 3. Check required tables
echo "<h2>3. Required Tables</h2>";
$required_tables = ['vehicles', 'fleet_assignments', 'vendor_kyc'];

foreach ($required_tables as $table) {
    $result = $con->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
        
        // Show column count
        $cols = $con->query("SHOW COLUMNS FROM $table");
        echo "&nbsp;&nbsp;&nbsp;Columns: " . $cols->num_rows . "<br>";
    } else {
        echo "❌ Table '$table' NOT FOUND<br>";
    }
}
echo "<hr>";

// 4. Check fleet_assignments payment columns
echo "<h2>4. Fleet Assignments Payment Columns</h2>";
$payment_columns = ['payment_status', 'payment_amount', 'advance_amount', 'remaining_amount', 'payment_date', 'payment_notes'];

$result = $con->query("SHOW COLUMNS FROM fleet_assignments");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($payment_columns as $col) {
    if (in_array($col, $existing_columns)) {
        echo "✅ Column '$col' exists<br>";
    } else {
        echo "❌ Column '$col' NOT FOUND<br>";
    }
}
echo "<hr>";

// 5. Check vehicles table data
echo "<h2>5. Vehicles Table Data</h2>";
$result = $con->query("SELECT COUNT(*) as count FROM vehicles");
$row = $result->fetch_assoc();
echo "Total vehicles: " . $row['count'] . "<br>";

if ($row['count'] > 0) {
    echo "✅ Vehicles data exists<br>";
    
    // Show sample vehicle
    $sample = $con->query("SELECT id, vehicle_number, vehicle_name, driver_name, status FROM vehicles LIMIT 1");
    if ($sample_row = $sample->fetch_assoc()) {
        echo "<br><strong>Sample Vehicle:</strong><br>";
        echo "ID: " . $sample_row['id'] . "<br>";
        echo "Number: " . $sample_row['vehicle_number'] . "<br>";
        echo "Name: " . $sample_row['vehicle_name'] . "<br>";
        echo "Driver: " . $sample_row['driver_name'] . "<br>";
        echo "Status: " . $sample_row['status'] . "<br>";
    }
} else {
    echo "⚠️ No vehicles found<br>";
}
echo "<hr>";

// 6. Check fleet_assignments table data
echo "<h2>6. Fleet Assignments Table Data</h2>";
$result = $con->query("SELECT COUNT(*) as count FROM fleet_assignments");
$row = $result->fetch_assoc();
echo "Total assignments: " . $row['count'] . "<br>";

if ($row['count'] > 0) {
    echo "✅ Assignments data exists<br>";
} else {
    echo "⚠️ No assignments found (this is OK if you haven't assigned any vehicles yet)<br>";
}
echo "<hr>";

// 7. Check file permissions
echo "<h2>7. File Permissions</h2>";
$dashboard_file = __DIR__ . '/dashboard.php';
if (file_exists($dashboard_file)) {
    echo "✅ dashboard.php exists<br>";
    echo "Path: $dashboard_file<br>";
    echo "Readable: " . (is_readable($dashboard_file) ? "✅ Yes" : "❌ No") . "<br>";
    echo "File size: " . filesize($dashboard_file) . " bytes<br>";
} else {
    echo "❌ dashboard.php NOT FOUND<br>";
}
echo "<hr>";

// 8. Check PHP extensions
echo "<h2>8. Required PHP Extensions</h2>";
$required_extensions = ['mysqli', 'json', 'mbstring'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension '$ext' loaded<br>";
    } else {
        echo "❌ Extension '$ext' NOT LOADED<br>";
    }
}
echo "<hr>";

// 9. Test a simple query
echo "<h2>9. Test Query</h2>";
try {
    $test_query = "SELECT v.id, v.vehicle_number, v.vehicle_name, v.status 
                   FROM vehicles v 
                   WHERE v.status = 'active' 
                   LIMIT 1";
    $result = $con->query($test_query);
    
    if ($result) {
        echo "✅ Query executed successfully<br>";
        echo "Rows returned: " . $result->num_rows . "<br>";
    } else {
        echo "❌ Query failed: " . $con->error . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 10. Summary
echo "<h2>10. Summary</h2>";
echo "<p><strong>If all checks pass (✅), then dashboard.php should work.</strong></p>";
echo "<p><strong>If you see any ❌, that's the issue you need to fix.</strong></p>";
echo "<br>";
echo "<p>Common issues:</p>";
echo "<ul>";
echo "<li>Missing payment columns in fleet_assignments table → Run the SQL file to add them</li>";
echo "<li>Database connection error → Check credentials in dashboard.php</li>";
echo "<li>PHP version too old → Upgrade to PHP 7.0+</li>";
echo "<li>Missing PHP extensions → Install required extensions</li>";
echo "</ul>";

$con->close();
?>
