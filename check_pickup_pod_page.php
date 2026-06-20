<?php
/**
 * Check what pickup-pod.php is actually outputting for the image
 * Upload to: https://abra-flowxai.abragroup.in/dashboard/check_pickup_pod_page.php
 */

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
$con->set_charset('utf8mb4');

echo "<h2>Pickup POD Page Debug</h2>";

// Get the latest record with a POD image
$res = $con->query("SELECT tracking, al_number, status, pickup_pod_image, pickup_pod_timestamp 
                    FROM courier 
                    WHERE pickup_pod_image IS NOT NULL AND pickup_pod_image != ''
                    ORDER BY pickup_pod_timestamp DESC 
                    LIMIT 3");

echo "<h3>Raw DB values:</h3>";
while ($row = $res->fetch_assoc()) {
    echo "<p><strong>Tracking:</strong> {$row['tracking']}<br>";
    echo "<strong>Status:</strong> {$row['status']}<br>";
    echo "<strong>pickup_pod_image raw value:</strong> <code>" . htmlspecialchars($row['pickup_pod_image']) . "</code><br>";
    echo "<strong>Direct img tag test:</strong><br>";
    echo "<img src='" . htmlspecialchars($row['pickup_pod_image']) . "' style='max-width:300px;border:2px solid green;' onerror=\"this.style.border='2px solid red'; this.alt='FAILED TO LOAD';\"><br>";
    echo "<strong>Clickable link:</strong> <a href='{$row['pickup_pod_image']}' target='_blank'>{$row['pickup_pod_image']}</a></p><hr>";
}

// Also check if there's a column mismatch — some dashboards use 'pod_image' not 'pickup_pod_image'
echo "<h3>Column names in courier table (POD related):</h3>";
$cols = $con->query("SHOW COLUMNS FROM courier LIKE '%pod%'");
while ($col = $cols->fetch_assoc()) {
    echo "<p><code>{$col['Field']}</code> — type: {$col['Type']}</p>";
}

// Check tsp_milestones columns
echo "<h3>tsp_milestones columns:</h3>";
$cols2 = $con->query("SHOW COLUMNS FROM tsp_milestones");
while ($col = $cols2->fetch_assoc()) {
    echo "<code>{$col['Field']}</code> &nbsp;";
}

mysqli_close($con);
?>
