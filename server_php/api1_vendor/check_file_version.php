<?php
/**
 * Check which version of get_notifications.php is on the server
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Check File Version on Server</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

$file_path = __DIR__ . '/get_notifications.php';

if (file_exists($file_path)) {
    echo "<p class='success'>✅ get_notifications.php exists</p>";
    
    // Read first 50 lines
    $lines = file($file_path);
    $first_lines = array_slice($lines, 0, 50);
    
    echo "<h2>First 50 lines of get_notifications.php:</h2>";
    echo "<pre>";
    echo htmlspecialchars(implode('', $first_lines));
    echo "</pre>";
    
    // Check for CORS headers
    $content = file_get_contents($file_path);
    
    echo "<h2>CORS Header Check:</h2>";
    
    if (strpos($content, "Access-Control-Allow-Origin: *") !== false) {
        echo "<p class='success'>✅ Has Access-Control-Allow-Origin header</p>";
    } else {
        echo "<p class='error'>❌ Missing Access-Control-Allow-Origin header</p>";
    }
    
    if (strpos($content, "Access-Control-Allow-Methods") !== false) {
        echo "<p class='success'>✅ Has Access-Control-Allow-Methods header</p>";
    } else {
        echo "<p class='error'>❌ Missing Access-Control-Allow-Methods header</p>";
    }
    
    if (strpos($content, "Access-Control-Allow-Headers") !== false) {
        echo "<p class='success'>✅ Has Access-Control-Allow-Headers header</p>";
    } else {
        echo "<p class='error'>❌ Missing Access-Control-Allow-Headers header</p>";
    }
    
    if (strpos($content, "Access-Control-Max-Age") !== false) {
        echo "<p class='success'>✅ Has Access-Control-Max-Age header (NEW VERSION)</p>";
    } else {
        echo "<p class='error'>❌ Missing Access-Control-Max-Age header (OLD VERSION)</p>";
    }
    
    // File info
    echo "<h2>File Information:</h2>";
    echo "<p>File path: <code>$file_path</code></p>";
    echo "<p>File size: " . filesize($file_path) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($file_path)) . "</p>";
    
} else {
    echo "<p class='error'>❌ get_notifications.php NOT FOUND at: $file_path</p>";
}

echo "<h2>Directory Contents:</h2>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li>$file</li>";
    }
}
echo "</ul>";
?>
