<?php
/**
 * Create Test Notification for Vendor
 * This will create a test notification for your vendor account
 */

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

echo "<h1>🔔 Create Test Notification for Vendor</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;}</style>";

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die("<p class='error'>❌ Connection failed: " . $con->connect_error . "</p>");
    }
    $con->set_charset('utf8mb4');
    
    // Your vendor Firebase UID
    $vendor_uid = 'qHa4BnKV1wSanQHE1QssUyF4wdH3';
    
    // Create test notification
    $type = 'kyc_approved';
    $title = '✅ Test Notification';
    $message = 'This is a test notification for your vendor account. Created at ' . date('Y-m-d H:i:s');
    
    $stmt = $con->prepare("INSERT INTO notifications (firebase_uid, type, title, message, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param('ssss', $vendor_uid, $type, $title, $message);
    
    if ($stmt->execute()) {
        $notification_id = $stmt->insert_id;
        echo "<p class='success'>✅ Test notification created successfully!</p>";
        echo "<p><strong>Notification ID:</strong> $notification_id</p>";
        echo "<p><strong>Firebase UID:</strong> $vendor_uid</p>";
        echo "<p><strong>Type:</strong> $type</p>";
        echo "<p><strong>Title:</strong> $title</p>";
        echo "<p><strong>Message:</strong> $message</p>";
        
        // Count unread notifications for this user
        $result = $con->query("SELECT COUNT(*) as count FROM notifications WHERE firebase_uid = '$vendor_uid' AND is_read = 0");
        $unread = $result->fetch_assoc()['count'];
        
        echo "<p class='success'>✅ You now have <strong>$unread</strong> unread notification(s)</p>";
        
        echo "<h2>Next Steps:</h2>";
        echo "<ol>";
        echo "<li>Upload get_notifications.php to server</li>";
        echo "<li>Upload mark_notification_read.php to server</li>";
        echo "<li>Open your vendor app</li>";
        echo "<li>You should see red badge with \"$unread\"</li>";
        echo "<li>Click notifications to see this test notification</li>";
        echo "</ol>";
        
    } else {
        echo "<p class='error'>❌ Failed to create notification: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
    $con->close();
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
}
?>
