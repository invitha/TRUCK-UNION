<?php
// Test file to diagnose Driver KYC system issues
require_once 'server_php/db_config.php';

echo "<h2>Driver KYC System Diagnosis</h2>";

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$con->set_charset('utf8mb4');

// 1. Check if driver_kyc table exists
echo "<h3>1. Database Table Check</h3>";
$table_check = mysqli_query($con, "SHOW TABLES LIKE 'driver_kyc'");
if (mysqli_num_rows($table_check) > 0) {
    echo "✓ driver_kyc table exists<br>";
    
    // Check table structure
    $structure = mysqli_query($con, "DESCRIBE driver_kyc");
    echo "<details><summary>Table Structure</summary>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($structure)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</details>";
} else {
    echo "❌ driver_kyc table does not exist<br>";
}

// 2. Check for existing driver KYC records
echo "<h3>2. Existing Driver KYC Records</h3>";
$kyc_count = mysqli_query($con, "SELECT COUNT(*) as total FROM driver_kyc");
$count_row = mysqli_fetch_assoc($kyc_count);
echo "Total driver KYC records: " . $count_row['total'] . "<br>";

if ($count_row['total'] > 0) {
    echo "<h4>Recent Records:</h4>";
    $recent = mysqli_query($con, "SELECT firebase_uid, driver_name, driver_mobile, kyc_status, created_at FROM driver_kyc ORDER BY created_at DESC LIMIT 5");
    echo "<table border='1'>";
    echo "<tr><th>Firebase UID</th><th>Name</th><th>Mobile</th><th>Status</th><th>Created</th></tr>";
    while ($row = mysqli_fetch_assoc($recent)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['firebase_uid']) . "</td>";
        echo "<td>" . htmlspecialchars($row['driver_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['driver_mobile']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kyc_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Check directory structure for documents
echo "<h3>3. Document Directory Check</h3>";
$upload_dirs = [
    '/home/royaldxd/crm.abra-logistic.com/uploads/driver_kyc_documents',
    '/home/royaldxd/crm.abra-logistic.com/api1/driver_kyc_documents',
    '../driver_kyc_documents'
];

foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ Directory exists: $dir<br>";
        $files = scandir($dir);
        $file_count = count($files) - 2; // Subtract . and ..
        echo "&nbsp;&nbsp;Files in directory: $file_count<br>";
        
        if ($file_count > 0 && $file_count <= 10) {
            echo "&nbsp;&nbsp;Sample files: " . implode(', ', array_slice($files, 2, 5)) . "<br>";
        }
    } else {
        echo "❌ Directory not found: $dir<br>";
    }
}

// 4. Test API endpoints
echo "<h3>4. API Endpoints Test</h3>";
$base_url = 'https://crm.abra-logistic.com/api1/';
$endpoints = [
    'get_driver_kyc_status.php',
    'submit_driver_kyc.php',
    'upload_driver_kyc_documents.php',
    'update_driver_kyc_status.php'
];

foreach ($endpoints as $endpoint) {
    $full_url = $base_url . $endpoint;
    echo "Testing: $endpoint - ";
    
    // Simple HEAD request to check if file exists
    $headers = @get_headers($full_url);
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "✓ Accessible<br>";
    } else {
        echo "❌ Not accessible or returns error<br>";
    }
}

// 5. Test document serving
echo "<h3>5. Document Serving Test</h3>";
$serve_urls = [
    'https://crm.abra-logistic.com/api1/serve_driver_kyc_document.php',
    'https://crm.abra-logistic.com/api1/serve_kyc_image.php'
];

foreach ($serve_urls as $url) {
    echo "Testing: $url - ";
    $headers = @get_headers($url);
    if ($headers && (strpos($headers[0], '200') !== false || strpos($headers[0], '400') !== false)) {
        echo "✓ Script accessible<br>";
    } else {
        echo "❌ Script not accessible<br>";
    }
}

// 6. Check for notifications table (for KYC status updates)
echo "<h3>6. Notifications System Check</h3>";
$notif_check = mysqli_query($con, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($notif_check) > 0) {
    echo "✓ notifications table exists<br>";
    
    // Count KYC-related notifications
    $kyc_notif = mysqli_query($con, "SELECT COUNT(*) as total FROM notifications WHERE type = 'kyc_update'");
    $notif_count = mysqli_fetch_assoc($kyc_notif);
    echo "KYC-related notifications: " . $notif_count['total'] . "<br>";
} else {
    echo "❌ notifications table does not exist<br>";
}

mysqli_close($con);
?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
details { margin: 10px 0; }
h3 { color: #0066cc; }
h4 { color: #666; }
</style>