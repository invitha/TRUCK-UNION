<?php
/**
 * Test Notifications System
 * Use this to verify notifications are working
 */

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

echo "<h1>🔔 Notification System Test</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        echo "<p class='error'>❌ Connection failed: " . $con->connect_error . "</p>";
        exit();
    }
    echo "<p class='success'>✅ Connected to database successfully</p>";
    $con->set_charset('utf8mb4');
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
    exit();
}

// Test 2: Check notifications table
echo "<h2>2. Check Notifications Table</h2>";
$result = $con->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "<p class='success'>✅ Notifications table exists</p>";
    
    // Show table structure
    $structure = $con->query("DESCRIBE notifications");
    echo "<p class='info'>Table structure:</p><pre>";
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "<p class='error'>❌ Notifications table does not exist</p>";
    echo "<p>Creating notifications table...</p>";
    
    $create_sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firebase_uid VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_firebase_uid (firebase_uid),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    )";
    
    if ($con->query($create_sql)) {
        echo "<p class='success'>✅ Notifications table created</p>";
    } else {
        echo "<p class='error'>❌ Failed to create table: " . $con->error . "</p>";
    }
}

// Test 3: Count notifications
echo "<h2>3. Notification Counts</h2>";
$total = $con->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$unread = $con->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
echo "<p class='info'>Total notifications: <strong>$total</strong></p>";
echo "<p class='info'>Unread notifications: <strong>$unread</strong></p>";

// Test 4: Show recent notifications
echo "<h2>4. Recent Notifications (Last 10)</h2>";
$recent = $con->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
if ($recent->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>ID</th><th>Firebase UID</th><th>Type</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th></tr>";
    while ($row = $recent->fetch_assoc()) {
        $read_badge = $row['is_read'] ? '✅ Read' : '🔴 Unread';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . substr($row['firebase_uid'], 0, 10) . "...</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>" . substr($row['message'], 0, 50) . "...</td>";
        echo "<td>$read_badge</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='info'>No notifications found</p>";
}

// Test 5: Test create notification function
echo "<h2>5. Test Create Notification</h2>";
$test_uid = 'test_' . time();
$test_type = 'test_notification';
$test_title = 'Test Notification';
$test_message = 'This is a test notification created at ' . date('Y-m-d H:i:s');

$stmt = $con->prepare("INSERT INTO notifications (firebase_uid, type, title, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $test_uid, $test_type, $test_title, $test_message);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    echo "<p class='success'>✅ Test notification created with ID: $new_id</p>";
    echo "<pre>";
    echo "Firebase UID: $test_uid\n";
    echo "Type: $test_type\n";
    echo "Title: $test_title\n";
    echo "Message: $test_message\n";
    echo "</pre>";
    
    // Clean up test notification
    $con->query("DELETE FROM notifications WHERE id = $new_id");
    echo "<p class='info'>Test notification deleted (cleanup)</p>";
} else {
    echo "<p class='error'>❌ Failed to create test notification: " . $stmt->error . "</p>";
}
$stmt->close();

// Test 6: Test API endpoints
echo "<h2>6. Test API Endpoints</h2>";
$base_url = 'https://crm.abra-logistic.com/api1/vendor';
echo "<p>Testing if API files exist:</p>";
echo "<ul>";
echo "<li><a href='$base_url/get_notifications.php' target='_blank'>get_notifications.php</a> - Click to test</li>";
echo "<li><a href='$base_url/mark_notification_read.php' target='_blank'>mark_notification_read.php</a> - Click to test</li>";
echo "<li><a href='$base_url/create_notification.php' target='_blank'>create_notification.php</a> - Click to test</li>";
echo "</ul>";

echo "<h2>✅ Test Complete</h2>";
echo "<p>If all tests passed, your notification system is working correctly!</p>";

$con->close();
?>
