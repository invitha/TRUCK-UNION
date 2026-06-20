<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Dashboard Diagnostic Test</h2>";

// Database connection
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        die('DB Connection Error: ' . $con->connect_error);
    }
    
    echo "<p style='color:green;'>✓ Database connected successfully</p>";
    
    $con->set_charset('utf8mb4');
    
    // Check if vehicles table exists
    $result = $con->query("SHOW TABLES LIKE 'vehicles'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green;'>✓ Vehicles table exists</p>";
    } else {
        die("<p style='color:red;'>✗ Vehicles table does NOT exist</p>");
    }
    
    // Check table structure
    echo "<h3>Vehicles Table Structure:</h3>";
    $result = $con->query("DESCRIBE vehicles");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $has_tonnage = false;
    $has_location = false;
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
        
        if ($row['Field'] == 'vehicle_tonnage') $has_tonnage = true;
        if ($row['Field'] == 'is_online') $has_location = true;
    }
    echo "</table>";
    
    echo "<h3>Column Status:</h3>";
    if ($has_tonnage) {
        echo "<p style='color:green;'>✓ vehicle_tonnage column exists</p>";
    } else {
        echo "<p style='color:red;'>✗ vehicle_tonnage column MISSING - Run add_tonnage_column.sql</p>";
    }
    
    if ($has_location) {
        echo "<p style='color:green;'>✓ Location tracking columns exist</p>";
    } else {
        echo "<p style='color:orange;'>⚠ Location tracking columns missing</p>";
    }
    
    // Try to query vehicles
    echo "<h3>Test Query:</h3>";
    $query = "SELECT id, vehicle_number, vehicle_name, vehicle_size_feet";
    if ($has_tonnage) $query .= ", vehicle_tonnage";
    if ($has_location) $query .= ", is_online";
    $query .= " FROM vehicles LIMIT 5";
    
    $result = $con->query($query);
    if ($result) {
        echo "<p style='color:green;'>✓ Query successful - " . $result->num_rows . " vehicles found</p>";
        
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Vehicle Number</th><th>Name</th><th>Size</th>";
            if ($has_tonnage) echo "<th>Tonnage</th>";
            if ($has_location) echo "<th>Online</th>";
            echo "</tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['vehicle_size_feet']) . "</td>";
                if ($has_tonnage) echo "<td>" . htmlspecialchars($row['vehicle_tonnage'] ?? 'NULL') . "</td>";
                if ($has_location) echo "<td>" . ($row['is_online'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red;'>✗ Query failed: " . $con->error . "</p>";
    }
    
    $con->close();
    
    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    if (!$has_tonnage) {
        echo "<p>1. Run this SQL command:</p>";
        echo "<pre>ALTER TABLE `vehicles` ADD COLUMN `vehicle_tonnage` VARCHAR(50) NULL AFTER `vehicle_size_feet`;</pre>";
    }
    echo "<p>2. After fixing, try accessing: <a href='dashboard.php'>dashboard.php</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
