<?php
/**
 * Diagnose get_notifications.php issues
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Diagnose get_notifications.php</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;}</style>";

$file_path = __DIR__ . '/get_notifications.php';

echo "<h2>1. File Exists Check</h2>";
if (file_exists($file_path)) {
    echo "<p class='success'>✅ File exists</p>";
    echo "<p>Path: <code>$file_path</code></p>";
    echo "<p>Size: " . filesize($file_path) . " bytes</p>";
    echo "<p>Modified: " . date('Y-m-d H:i:s', filemtime($file_path)) . "</p>";
} else {
    echo "<p class='error'>❌ File NOT found</p>";
    exit;
}

echo "<h2>2. File Permissions</h2>";
$perms = fileperms($file_path);
$perms_string = substr(sprintf('%o', $perms), -4);
echo "<p>Permissions: <code>$perms_string</code></p>";

$owner = posix_getpwuid(fileowner($file_path));
$group = posix_getgrgid(filegroup($file_path));
echo "<p>Owner: <code>{$owner['name']}</code></p>";
echo "<p>Group: <code>{$group['name']}</code></p>";

echo "<h2>3. File Content (First 100 lines)</h2>";
$lines = file($file_path);
$first_lines = array_slice($lines, 0, 100);
echo "<pre>";
echo htmlspecialchars(implode('', $first_lines));
echo "</pre>";

echo "<h2>4. PHP Syntax Check</h2>";
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_var);
if ($return_var === 0) {
    echo "<p class='success'>✅ No syntax errors</p>";
} else {
    echo "<p class='error'>❌ Syntax errors found:</p>";
    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
}

echo "<h2>5. Test Direct Execution</h2>";
echo "<p>Testing with POST data...</p>";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$test_data = json_encode([
    'firebase_uid' => 'qHa4BnKV1wSanQHE1QssUyF4wdH3',
    'limit' => 10,
    'offset' => 0
]);

// Capture output
ob_start();
$_POST_backup = $_POST;
file_put_contents('php://input', $test_data);

try {
    include($file_path);
    $output = ob_get_clean();
    
    echo "<p class='success'>✅ File executed without fatal errors</p>";
    echo "<h3>Output:</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to decode as JSON
    $json = json_decode($output, true);
    if ($json !== null) {
        echo "<p class='success'>✅ Valid JSON output</p>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<p class='error'>❌ Output is not valid JSON</p>";
        echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
    }
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "<p class='error'>❌ Error during execution:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    if (!empty($output)) {
        echo "<h3>Output before error:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
}

$_POST = $_POST_backup;

echo "<h2>6. Database Connection Test</h2>";
try {
    $con = new mysqli('localhost', 'royaldxd_user', 'meg_layout312', 'royaldxd_abra_crm');
    if ($con->connect_error) {
        echo "<p class='error'>❌ Database connection failed: " . $con->connect_error . "</p>";
    } else {
        echo "<p class='success'>✅ Database connection successful</p>";
        
        // Check notifications table
        $result = $con->query("SELECT COUNT(*) as count FROM notifications WHERE firebase_uid = 'qHa4BnKV1wSanQHE1QssUyF4wdH3'");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Notifications for test user: <strong>{$row['count']}</strong></p>";
        }
        
        $con->close();
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
}
