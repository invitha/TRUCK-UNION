<?php
/**
 * Diagnose Notification Issues
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Notification Diagnostic Tool</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Check if files exist
echo "<h2>1. Check if notification files exist</h2>";

$files = [
    'get_notifications.php',
    'mark_notification_read.php',
    'create_notification.php',
    'test_notifications.php',
    'diagnose_notifications.php'
];

$current_dir = __DIR__;
echo "<p class='info'>Current directory: <code>$current_dir</code></p>";

foreach ($files as $file) {
    $path = $current_dir . '/' . $file;
    if (file_exists($path)) {
        echo "<p class='success'>✅ $file exists</p>";
    } else {
        echo "<p class='error'>❌ $file NOT FOUND</p>";
    }
}

// Check URLs
echo "<h2>2. Test API URLs</h2>";
$base_url = 'https://crm.abra-logistic.com/api1/vendor';

echo "<p>Click these links to test:</p>";
echo "<ul>";
echo "<li><a href='$base_url/get_notifications.php' target='_blank'>get_notifications.php</a></li>";
echo "<li><a href='$base_url/mark_notification_read.php' target='_blank'>mark_notification_read.php</a></li>";
echo "<li><a href='$base_url/test_notifications.php' target='_blank'>test_notifications.php</a></li>";
echo "</ul>";

// Test database connection
echo "<h2>3. Test Database Connection</h2>";

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        echo "<p class='error'>❌ Connection failed: " . $con->connect_error . "</p>";
    } else {
        echo "<p class='success'>✅ Database connected</p>";
        
        // Count notifications
        $result = $con->query("SELECT COUNT(*) as total FROM notifications");
        $total = $result->fetch_assoc()['total'];
        
        $result = $con->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
        $unread = $result->fetch_assoc()['unread'];
        
        echo "<p class='info'>Total notifications: <strong>$total</strong></p>";
        echo "<p class='info'>Unread notifications: <strong>$unread</strong></p>";
        
        // Show unread by user
        echo "<h3>Unread notifications by user:</h3>";
        $result = $con->query("
            SELECT firebase_uid, COUNT(*) as count 
            FROM notifications 
            WHERE is_read = 0 
            GROUP BY firebase_uid
        ");
        
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
            echo "<tr><th>Firebase UID</th><th>Unread Count</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . substr($row['firebase_uid'], 0, 20) . "...</td>";
                echo "<td><strong>{$row['count']}</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>No unread notifications</p>";
        }
        
        $con->close();
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test CORS headers
echo "<h2>4. Test CORS Headers</h2>";
echo "<p>Testing if get_notifications.php has CORS headers...</p>";

$test_url = $base_url . '/get_notifications.php';
$ch = curl_init($test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status Code: <strong>$http_code</strong></p>";

if ($http_code == 200) {
    echo "<p class='success'>✅ File is accessible</p>";
    
    if (strpos($response, 'Access-Control-Allow-Origin') !== false) {
        echo "<p class='success'>✅ CORS headers present</p>";
    } else {
        echo "<p class='error'>❌ CORS headers missing</p>";
    }
} else if ($http_code == 404) {
    echo "<p class='error'>❌ File not found (404)</p>";
    echo "<p class='warning'>⚠️ The file is NOT uploaded to the server!</p>";
} else {
    echo "<p class='warning'>⚠️ Unexpected status code: $http_code</p>";
}

// Show expected vs actual paths
echo "<h2>5. Path Information</h2>";
echo "<p><strong>Expected server path:</strong></p>";
echo "<pre>/home/royaldxd/crm.abra-logistic.com/api1/vendor/get_notifications.php</pre>";

echo "<p><strong>Expected URL:</strong></p>";
echo "<pre>https://crm.abra-logistic.com/api1/vendor/get_notifications.php</pre>";

echo "<p><strong>Actual file location:</strong></p>";
echo "<pre>$current_dir/get_notifications.php</pre>";

// Test POST request
echo "<h2>6. Test POST Request</h2>";
echo "<p>Testing get_notifications.php with POST data...</p>";

$test_uid = 'qHa4BnKV1wSanQHE1QssUyF4wdH3';
$post_data = json_encode([
    'firebase_uid' => $test_uid,
    'timestamp' => time() * 1000
]);

$ch = curl_init($base_url . '/get_notifications.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: <strong>$http_code</strong></p>";
echo "<p>Response:</p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($http_code == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['status'])) {
        if ($data['status'] === 'success') {
            echo "<p class='success'>✅ API is working correctly!</p>";
            echo "<p class='info'>Unread count: <strong>" . ($data['unread_count'] ?? 0) . "</strong></p>";
        } else {
            echo "<p class='warning'>⚠️ API returned error: " . ($data['message'] ?? 'Unknown') . "</p>";
        }
    }
}

echo "<h2>✅ Diagnostic Complete</h2>";
echo "<p>If all tests passed, the notification system should work in the app.</p>";
echo "<p>If you're still getting CORS errors in the app, try:</p>";
echo "<ul>";
echo "<li>Clear app cache and restart</li>";
echo "<li>Check browser console for exact error</li>";
echo "<li>Verify Firebase UID is correct</li>";
echo "</ul>";
?>
