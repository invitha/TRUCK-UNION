<?php
/**
 * Reads the content of pickup-pod.php so we can debug the image modal
 * Upload to: https://abra-flowxai.abragroup.in/dashboard/read_pickup_pod_php.php
 */
$file = __DIR__ . '/pickup-pod.php';
if (file_exists($file)) {
    echo '<pre style="white-space:pre-wrap;word-break:break-all;">';
    echo htmlspecialchars(file_get_contents($file));
    echo '</pre>';
} else {
    echo "File not found at: $file";
    echo "<br>Files in this dir:<br>";
    foreach (glob(__DIR__ . '/*.php') as $f) {
        echo basename($f) . "<br>";
    }
}
?>
