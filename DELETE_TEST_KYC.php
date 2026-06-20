<?php
// SIMPLE DELETE SCRIPT - Remove test KYC data

header('Content-Type: text/html');

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        die('❌ Database connection failed: ' . $con->connect_error);
    }
    
    $con->set_charset('utf8mb4');
    
    echo "<h2>🗑️ DELETING TEST KYC DATA...</h2>";
    
    // Delete the specific test record (ID = 1)
    $delete_query = "DELETE FROM driver_kyc WHERE id = 1";
    
    if ($con->query($delete_query)) {
        $affected = $con->affected_rows;
        if ($affected > 0) {
            echo "<p style='color: green; font-size: 18px;'>✅ SUCCESS! Deleted $affected test record(s)</p>";
        } else {
            echo "<p style='color: orange; font-size: 18px;'>⚠️ No records found to delete (already cleaned?)</p>";
        }
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ ERROR: " . $con->error . "</p>";
    }
    
    // Show remaining records
    echo "<h3>REMAINING RECORDS IN DATABASE:</h3>";
    
    $check_query = "SELECT * FROM driver_kyc ORDER BY created_at DESC";
    $result = $con->query($check_query);
    
    if ($result->num_rows == 0) {
        echo "<p style='color: green; font-size: 16px;'>✅ Database is now CLEAN - No KYC records found</p>";
        echo "<p style='color: blue;'>👍 Your app will now show empty fields for new KYC submission</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Firebase UID</th><th>Name</th><th>Mobile</th><th>Status</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['firebase_uid']) . "</td>";
            echo "<td>" . htmlspecialchars($row['driver_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['driver_mobile']) . "</td>";
            echo "<td>" . htmlspecialchars($row['kyc_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERROR: " . $e->getMessage() . "</p>";
}

echo "<br><br>";
echo "<h3>NEXT STEPS:</h3>";
echo "<ol>";
echo "<li><strong>Restart your driver app</strong></li>";
echo "<li><strong>Login again as driver</strong></li>";
echo "<li><strong>Go to KYC page</strong> - should be empty now</li>";
echo "<li><strong>Enter your real information</strong></li>";
echo "<li><strong>Submit KYC</strong> - should work without errors</li>";
echo "</ol>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { margin-top: 10px; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
</style>