<?php
/**
 * Test Database Connection Path
 * This will help us find the correct path to database.php
 */

header('Content-Type: text/plain');

echo "Testing different paths to find database.php...\n\n";

// Test Path 1: ../../database.php (two levels up)
$path1 = '../../database.php';
echo "Path 1: $path1\n";
echo "Exists: " . (file_exists($path1) ? "YES" : "NO") . "\n";
echo "Real path: " . (file_exists($path1) ? realpath($path1) : "N/A") . "\n\n";

// Test Path 2: ../../dashboard/database.php
$path2 = '../../dashboard/database.php';
echo "Path 2: $path2\n";
echo "Exists: " . (file_exists($path2) ? "YES" : "NO") . "\n";
echo "Real path: " . (file_exists($path2) ? realpath($path2) : "N/A") . "\n\n";

// Test Path 3: ../customer/ (check how customer does it)
$path3 = '../customer/';
echo "Path 3: $path3\n";
echo "Exists: " . (file_exists($path3) ? "YES" : "NO") . "\n\n";

// Show current directory
echo "Current directory: " . __DIR__ . "\n";
echo "Current file: " . __FILE__ . "\n";

// Try to find database.php by scanning parent directories
echo "\n--- Scanning for database.php ---\n";
$current = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $current = dirname($current);
    $db_path = $current . '/database.php';
    echo "Level $i: $db_path - " . (file_exists($db_path) ? "FOUND!" : "not found") . "\n";
    
    $dashboard_path = $current . '/dashboard/database.php';
    echo "Level $i (dashboard): $dashboard_path - " . (file_exists($dashboard_path) ? "FOUND!" : "not found") . "\n";
}
?>
