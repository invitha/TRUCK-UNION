<?php
// Check if geocoding columns exist and add them if missing
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) die('DB Error: ' . $con->connect_error);
$con->set_charset('utf8mb4');

echo "<h2>Checking Geocoding Columns</h2>";
echo "<pre>";

// Check current table structure
$result = $con->query("DESCRIBE vehicles");
echo "Current columns in vehicles table:\n";
echo str_repeat("-", 60) . "\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-30s %-20s\n", $row['Field'], $row['Type']);
}

echo "\n\n";

// Check if geocoded columns exist
$has_city  = $con->query("SHOW COLUMNS FROM vehicles LIKE 'geocoded_city'")->num_rows > 0;
$has_state = $con->query("SHOW COLUMNS FROM vehicles LIKE 'geocoded_state'")->num_rows > 0;

echo "Geocoded columns status:\n";
echo str_repeat("-", 60) . "\n";
echo "geocoded_city:  " . ($has_city  ? "✓ EXISTS" : "✗ MISSING") . "\n";
echo "geocoded_state: " . ($has_state ? "✓ EXISTS" : "✗ MISSING") . "\n";

// Add missing columns
if (!$has_city) {
    echo "\nAdding geocoded_city column...\n";
    if ($con->query("ALTER TABLE vehicles ADD COLUMN geocoded_city VARCHAR(120) DEFAULT NULL")) {
        echo "✓ geocoded_city column added successfully\n";
    } else {
        echo "✗ Failed to add geocoded_city: " . $con->error . "\n";
    }
}

if (!$has_state) {
    echo "\nAdding geocoded_state column...\n";
    if ($con->query("ALTER TABLE vehicles ADD COLUMN geocoded_state VARCHAR(120) DEFAULT NULL")) {
        echo "✓ geocoded_state column added successfully\n";
    } else {
        echo "✗ Failed to add geocoded_state: " . $con->error . "\n";
    }
}

echo "\n\n";

// Show sample data
$result = $con->query("SELECT id, vehicle_number, last_latitude, last_longitude, location_address, geocoded_city, geocoded_state FROM vehicles LIMIT 3");
echo "Sample vehicle data:\n";
echo str_repeat("-", 60) . "\n";
while ($row = $result->fetch_assoc()) {
    echo "Vehicle #" . $row['id'] . " (" . $row['vehicle_number'] . "):\n";
    echo "  GPS: " . $row['last_latitude'] . ", " . $row['last_longitude'] . "\n";
    echo "  location_address: " . ($row['location_address'] ?: 'NULL') . "\n";
    echo "  geocoded_city: " . ($row['geocoded_city'] ?: 'NULL') . "\n";
    echo "  geocoded_state: " . ($row['geocoded_state'] ?: 'NULL') . "\n";
    echo "\n";
}

echo "</pre>";
$con->close();
?>
