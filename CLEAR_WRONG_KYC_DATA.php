<?php
// DIAGNOSTIC TOOL - Check and clear wrong KYC data

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        throw new Exception('Database connection failed: ' . $con->connect_error);
    }
    
    $con->set_charset('utf8mb4');
    
    echo "<h2>DRIVER KYC DATABASE DIAGNOSTIC</h2>";
    
    // Check if driver_kyc table exists
    $table_check = $con->query("SHOW TABLES LIKE 'driver_kyc'");
    if ($table_check->num_rows == 0) {
        echo "<p style='color: red;'>❌ driver_kyc table does NOT exist!</p>";
        exit();
    }
    
    echo "<p style='color: green;'>✅ driver_kyc table exists</p>";
    
    // Show all records in driver_kyc table
    $query = "SELECT * FROM driver_kyc ORDER BY created_at DESC";
    $result = $con->query($query);
    
    echo "<h3>ALL DRIVER KYC RECORDS:</h3>";
    
    if ($result->num_rows == 0) {
        echo "<p style='color: orange;'>⚠️ No records found in driver_kyc table</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Firebase UID</th><th>Driver Name</th><th>Mobile</th><th>Email</th>";
        echo "<th>Aadhar</th><th>PAN</th><th>License</th><th>Status</th><th>Created</th><th>Action</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['firebase_uid']) . "</td>";
            echo "<td>" . htmlspecialchars($row['driver_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['driver_mobile']) . "</td>";
            echo "<td>" . htmlspecialchars($row['driver_email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['aadhar_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['pan_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['license_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['kyc_status']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "<td><a href='?delete_id=" . $row['id'] . "' onclick=\"return confirm('Delete this record?')\">DELETE</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Handle delete request
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $delete_query = "DELETE FROM driver_kyc WHERE id = ?";
        $delete_stmt = $con->prepare($delete_query);
        $delete_stmt->bind_param('i', $delete_id);
        
        if ($delete_stmt->execute()) {
            echo "<script>alert('Record deleted successfully!'); window.location.href = window.location.pathname;</script>";
        } else {
            echo "<p style='color: red;'>❌ Failed to delete record</p>";
        }
    }
    
    // Clear all test data button
    echo "<br><br>";
    echo "<a href='?clear_all=1' onclick=\"return confirm('Clear ALL driver KYC records? This cannot be undone!')\" style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🗑️ CLEAR ALL TEST DATA</a>";
    
    if (isset($_GET['clear_all'])) {
        $clear_query = "DELETE FROM driver_kyc WHERE driver_name LIKE '%test%' OR driver_email LIKE '%test%' OR driver_mobile IN ('123', '1234567890', '0000000000')";
        if ($con->query($clear_query)) {
            $affected = $con->affected_rows;
            echo "<script>alert('Cleared $affected test records!'); window.location.href = window.location.pathname;</script>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { margin-top: 10px; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
</style>