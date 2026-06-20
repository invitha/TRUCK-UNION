<?php
/**
 * Diagnose KYC Image Serving Issues
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Diagnose KYC Image Serving</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Check if serve_kyc_image.php exists
$serve_file = __DIR__ . '/serve_kyc_image.php';
echo "<h2>1. Check serve_kyc_image.php</h2>";
if (file_exists($serve_file)) {
    echo "<p class='success'>✅ serve_kyc_image.php exists</p>";
    echo "<p>Path: <code>$serve_file</code></p>";
} else {
    echo "<p class='error'>❌ serve_kyc_image.php NOT FOUND</p>";
    echo "<p>Expected at: <code>$serve_file</code></p>";
}

// Check uploads directory
$upload_dir = '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents';
echo "<h2>2. Check Uploads Directory</h2>";
if (is_dir($upload_dir)) {
    echo "<p class='success'>✅ Uploads directory exists</p>";
    echo "<p>Path: <code>$upload_dir</code></p>";
    
    // List subdirectories (user folders)
    $dirs = scandir($upload_dir);
    $user_dirs = array_filter($dirs, function($d) use ($upload_dir) {
        return $d != '.' && $d != '..' && is_dir($upload_dir . '/' . $d);
    });
    
    if (count($user_dirs) > 0) {
        echo "<p class='success'>✅ Found " . count($user_dirs) . " user folders</p>";
        echo "<h3>User Folders:</h3><ul>";
        foreach ($user_dirs as $dir) {
            $user_path = $upload_dir . '/' . $dir;
            $files = array_diff(scandir($user_path), ['.', '..']);
            echo "<li><strong>$dir</strong> (" . count($files) . " files)";
            if (count($files) > 0) {
                echo "<ul>";
                foreach ($files as $file) {
                    $file_path = $user_path . '/' . $file;
                    $size = filesize($file_path);
                    $readable = is_readable($file_path) ? '✅' : '❌';
                    echo "<li>$readable $file (" . number_format($size) . " bytes)</li>";
                }
                echo "</ul>";
            }
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ No user folders found</p>";
    }
} else {
    echo "<p class='error'>❌ Uploads directory NOT FOUND</p>";
    echo "<p>Expected at: <code>$upload_dir</code></p>";
}

// Test URL construction
echo "<h2>3. Test URL Construction</h2>";
$test_uid = 'qHa4BnKV1wSanQHE1QssUyF4wdH3';
$test_file = 'photo_1778154699_69fc7ccb4b727.jpg';
$test_url = "https://crm.abra-logistic.com/serve_kyc_image.php?uid=$test_uid&file=$test_file";
echo "<p>Test URL: <a href='$test_url' target='_blank'>$test_url</a></p>";

// Check database for actual file names
echo "<h2>4. Check Database for KYC Documents</h2>";
try {
    $con = new mysqli('localhost', 'royaldxd_user', 'meg_layout312', 'royaldxd_abra_crm');
    if ($con->connect_error) {
        echo "<p class='error'>❌ Database connection failed</p>";
    } else {
        $result = $con->query("SELECT firebase_uid, photo, pan_card, aadhar_card, gst_certificate FROM vendor_kyc WHERE status = 'verified' LIMIT 5");
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
            echo "<tr><th>Firebase UID</th><th>Photo</th><th>PAN</th><th>Aadhar</th><th>GST</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . substr($row['firebase_uid'], 0, 10) . "...</td>";
                echo "<td>" . ($row['photo'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row['pan_card'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row['aadhar_card'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row['gst_certificate'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No verified KYC records found</p>";
        }
        $con->close();
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>5. Recommendations</h2>";
echo "<ul>";
echo "<li>Upload <code>serve_kyc_image.php</code> to <code>/home/royaldxd/crm.abra-logistic.com/</code></li>";
echo "<li>Set permissions: <code>chmod 644 serve_kyc_image.php</code></li>";
echo "<li>Ensure uploads directory has correct permissions: <code>chmod 755 $upload_dir</code></li>";
echo "<li>Ensure user folders have correct permissions: <code>chmod 755 $upload_dir/*</code></li>";
echo "<li>Ensure image files have correct permissions: <code>chmod 644 $upload_dir/*/*.jpg</code></li>";
echo "</ul>";
