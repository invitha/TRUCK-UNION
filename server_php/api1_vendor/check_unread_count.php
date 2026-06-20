<?php
/**
 * Quick check of unread notification count
 */

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$test_uid = 'qHa4BnKV1wSanQHE1QssUyF4wdH3';

echo "<h1>🔔 Check Unread Notifications</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;}</style>";

try {
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        die("<p class='error'>❌ Database connection failed</p>");
    }
    
    echo "<p class='success'>✅ Connected to database</p>";
    
    // Get total notifications
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM notifications WHERE firebase_uid = ?");
    $stmt->bind_param('s', $test_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = $row['total'];
    $stmt->close();
    
    // Get unread notifications
    $stmt = $con->prepare("SELECT COUNT(*) as unread FROM notifications WHERE firebase_uid = ? AND is_read = 0");
    $stmt->bind_param('s', $test_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread = $row['unread'];
    $stmt->close();
    
    echo "<h2>For User: $test_uid</h2>";
    echo "<p><strong>Total Notifications:</strong> $total</p>";
    echo "<p><strong>Unread Notifications:</strong> <span style='color:red;font-size:24px;font-weight:bold;'>$unread</span></p>";
    
    if ($unread > 0) {
        echo "<p class='success'>✅ Badge should show: $unread</p>";
        
        // Show the unread notifications
        echo "<h3>Unread Notifications:</h3>";
        $stmt = $con->prepare("SELECT id, type, title, message, created_at FROM notifications WHERE firebase_uid = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->bind_param('s', $test_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['type']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>{$row['message']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        $stmt->close();
    } else {
        echo "<p class='error'>❌ No unread notifications - badge will not show</p>";
        echo "<p><a href='create_test_notification_for_vendor.php'>Create a test notification</a></p>";
    }
    
    $con->close();
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
