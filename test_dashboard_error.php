<?php
/**
 * Dashboard Error Checker
 * This will show the EXACT error in dashboard.php
 */

// Enable ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Testing Dashboard.php</h1>";
echo "<hr>";

// Test 1: Check if file exists
echo "<h2>1. File Check</h2>";
$dashboard_file = __DIR__ . '/dashboard.php';
if (file_exists($dashboard_file)) {
    echo "✅ dashboard.php exists<br>";
    echo "Path: $dashboard_file<br>";
    echo "Size: " . filesize($dashboard_file) . " bytes<br>";
} else {
    echo "❌ dashboard.php NOT FOUND<br>";
    exit;
}
echo "<hr>";

// Test 2: Check PHP syntax
echo "<h2>2. PHP Syntax Check</h2>";
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($dashboard_file) . " 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "✅ No syntax errors<br>";
} else {
    echo "❌ SYNTAX ERRORS FOUND:<br>";
    echo "<pre style='background:#fee2e2;padding:15px;color:#991b1b;'>";
    echo implode("\n", $output);
    echo "</pre>";
}
echo "<hr>";

// Test 3: Try to include the file and catch errors
echo "<h2>3. Runtime Test</h2>";
echo "Attempting to load dashboard.php...<br><br>";

ob_start();
try {
    // This will show the actual error
    include $dashboard_file;
    echo "<br><br>✅ Dashboard loaded successfully!<br>";
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ ERROR CAUGHT:<br>";
    echo "<pre style='background:#fee2e2;padding:15px;color:#991b1b;'>";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
$output_content = ob_get_clean();

// Show any PHP errors that occurred
$last_error = error_get_last();
if ($last_error && ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
    echo "❌ PHP ERROR:<br>";
    echo "<pre style='background:#fee2e2;padding:15px;color:#991b1b;'>";
    echo "Type: " . $last_error['type'] . "\n";
    echo "Message: " . $last_error['message'] . "\n";
    echo "File: " . $last_error['file'] . "\n";
    echo "Line: " . $last_error['line'];
    echo "</pre>";
}

// Show the output
if (!empty($output_content)) {
    echo "<div style='margin-top:20px;'>";
    echo "<h3>Dashboard Output:</h3>";
    echo "<div style='border:2px solid #10b981;padding:10px;background:#f0fdf4;'>";
    echo $output_content;
    echo "</div>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If you see errors above, that's what's breaking dashboard.php</p>";
echo "<p>If no errors, then dashboard.php is working fine!</p>";
?>
