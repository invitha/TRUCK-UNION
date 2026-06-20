<?php
/**
 * Check vendor_kyc table structure
 */

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die("Connection failed: " . $con->connect_error);
    }
    
    echo "<h1>Vendor KYC Table Structure</h1>";
    echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:12px;text-align:left;} th{background:#667eea;color:white;}</style>";
    
    // Get table structure
    $result = $con->query("DESCRIBE vendor_kyc");
    
    if ($result) {
        echo "<h2>Table Columns:</h2>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Get sample data
    echo "<h2>Sample Data (First Record):</h2>";
    $sample = $con->query("SELECT * FROM vendor_kyc LIMIT 1");
    if ($sample && $sample->num_rows > 0) {
        $data = $sample->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Column</th><th>Value</th></tr>";
        foreach ($data as $key => $value) {
            echo "<tr>";
            echo "<td><strong>$key</strong></td>";
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No data in table yet.</p>";
    }
    
    $con->close();
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
