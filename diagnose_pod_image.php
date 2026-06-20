<?php
/**
 * POD Image Diagnostics
 * Upload to: https://abra-flowxai.abragroup.in/dashboard/diagnose_pod_image.php
 * Then open in browser to see what's happening
 */

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
$con->set_charset('utf8mb4');

echo "<h2>POD Image Diagnostics</h2>";

// ── 1. Show last 5 rows with pickup_pod_image ────────────────────────────
echo "<h3>1. Recent records with pickup_pod_image</h3>";
$res = $con->query("SELECT tracking, status, pickup_pod_image, pickup_pod_timestamp 
                    FROM courier 
                    WHERE pickup_pod_image IS NOT NULL AND pickup_pod_image != ''
                    ORDER BY pickup_pod_timestamp DESC 
                    LIMIT 5");

if ($res && $res->num_rows > 0) {
    echo "<table border='1' cellpadding='6'>";
    echo "<tr><th>Tracking</th><th>Status</th><th>pickup_pod_image URL</th><th>Timestamp</th><th>URL Reachable?</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $url = $row['pickup_pod_image'];
        // Try to check if URL is reachable
        $headers = @get_headers($url);
        $reachable = ($headers && strpos($headers[0], '200')) ? "<span style='color:green'>✅ 200 OK</span>" : "<span style='color:red'>❌ " . ($headers ? $headers[0] : 'No response') . "</span>";
        echo "<tr>
            <td>{$row['tracking']}</td>
            <td>{$row['status']}</td>
            <td><a href='$url' target='_blank'>$url</a></td>
            <td>{$row['pickup_pod_timestamp']}</td>
            <td>$reachable</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>No records found with pickup_pod_image</p>";
}

// ── 2. Check if uploads folder exists and is readable ────────────────────
echo "<h3>2. Server upload folder check</h3>";
$webroot = $_SERVER['DOCUMENT_ROOT'];
$paths_to_check = [
    $webroot . '/uploads/pickup-photos/',
    $webroot . '/dashboard/uploads/pickup-photos/',
    dirname(__DIR__) . '/uploads/pickup-photos/',
    __DIR__ . '/uploads/pickup-photos/',
];

foreach ($paths_to_check as $path) {
    $exists   = file_exists($path) ? "✅ Exists" : "❌ NOT found";
    $readable = file_exists($path) && is_readable($path) ? "✅ Readable" : "❌ Not readable";
    $files    = file_exists($path) ? count(glob($path . '*')) . " files" : "-";
    echo "<p><code>$path</code> — $exists | $readable | $files</p>";
}

// ── 3. Show what DOCUMENT_ROOT and this file's path are ─────────────────
echo "<h3>3. Server path info</h3>";
echo "<p>DOCUMENT_ROOT: <code>" . $_SERVER['DOCUMENT_ROOT'] . "</code></p>";
echo "<p>This file: <code>" . __FILE__ . "</code></p>";
echo "<p>dirname(__DIR__): <code>" . dirname(__DIR__) . "</code></p>";

// ── 4. Check tsp_milestones for POD images too ───────────────────────────
echo "<h3>4. Recent tsp_milestones POD images</h3>";
$res2 = $con->query("SELECT delivery_id, tracking, trStatus, trPODimage, created_at 
                     FROM tsp_milestones 
                     WHERE trPODimage IS NOT NULL AND trPODimage != ''
                     ORDER BY created_at DESC 
                     LIMIT 5");
if ($res2 && $res2->num_rows > 0) {
    echo "<table border='1' cellpadding='6'>";
    echo "<tr><th>Driver</th><th>Tracking</th><th>Status</th><th>POD URL</th></tr>";
    while ($row = $res2->fetch_assoc()) {
        $url = $row['trPODimage'];
        echo "<tr>
            <td>{$row['delivery_id']}</td>
            <td>{$row['tracking']}</td>
            <td>{$row['trStatus']}</td>
            <td><a href='$url' target='_blank'>$url</a></td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange'>No POD images in tsp_milestones</p>";
}

mysqli_close($con);
?>
