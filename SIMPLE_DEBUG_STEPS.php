<?php
// SIMPLE DEBUG TOOL - Upload this to your server root and run it
require_once 'server_php/db_config.php';

echo "<h2>🔍 DRIVER KYC DEBUG TOOL</h2>";
echo "<p>This will help us find where the KYC data is going...</p>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        echo "❌ Database connection FAILED: " . $con->connect_error . "<br>";
        exit();
    } else {
        echo "✅ Database connection OK<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit();
}

// Check if driver_kyc table exists
echo "<h3>2. Check Driver KYC Table</h3>";
$table_check = mysqli_query($con, "SHOW TABLES LIKE 'driver_kyc'");
if (mysqli_num_rows($table_check) > 0) {
    echo "✅ driver_kyc table EXISTS<br>";
} else {
    echo "❌ driver_kyc table MISSING<br>";
    echo "🔧 <strong>SOLUTION: Create the table first!</strong><br>";
}

// Check for any driver KYC records
echo "<h3>3. Check Driver KYC Records</h3>";
$check_records = mysqli_query($con, "SELECT COUNT(*) as total FROM driver_kyc");
if ($check_records) {
    $row = mysqli_fetch_assoc($check_records);
    echo "📊 Total driver KYC records: " . $row['total'] . "<br>";
    
    if ($row['total'] > 0) {
        echo "<h4>Recent Records:</h4>";
        $recent = mysqli_query($con, "SELECT * FROM driver_kyc ORDER BY created_at DESC LIMIT 3");
        while ($record = mysqli_fetch_assoc($recent)) {
            echo "🔹 Firebase UID: " . $record['firebase_uid'] . " | Name: " . $record['driver_name'] . " | Mobile: " . $record['driver_mobile'] . " | Status: " . $record['kyc_status'] . "<br>";
        }
    } else {
        echo "❌ NO RECORDS FOUND - This is your problem!<br>";
    }
} else {
    echo "❌ Cannot check records: " . mysqli_error($con) . "<br>";
}

// Check API endpoints
echo "<h3>4. Check API Files</h3>";
$api_files = [
    'server_php/api1_vendor/submit_driver_kyc.php',
    'server_php/api1_vendor/get_driver_kyc_status.php',
    'server_php/get_all_driver_kyc.php'
];

foreach ($api_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file EXISTS<br>";
    } else {
        echo "❌ $file MISSING<br>";
    }
}

// Test sample insert
echo "<h3>5. Test Sample Insert</h3>";
$test_insert = "INSERT INTO driver_kyc (firebase_uid, driver_name, driver_mobile, driver_email, aadhar_number, pan_number, license_number, kyc_status) 
                VALUES ('test_123', 'Test Driver', '9876543210', 'test@example.com', '123456789012', 'ABCDE1234F', 'DL123456', 'submitted')";

if (mysqli_query($con, $test_insert)) {
    echo "✅ Sample insert SUCCESSFUL<br>";
    echo "🔄 Now check if this appears in admin panel<br>";
} else {
    echo "❌ Sample insert FAILED: " . mysqli_error($con) . "<br>";
}

mysqli_close($con);

echo "<hr>";
echo "<h3>📋 QUICK FIXES TO TRY:</h3>";
echo "<p>1. If table is missing → Run create_driver_kyc_table.sql</p>";
echo "<p>2. If API files missing → Upload them to /api1/</p>";
echo "<p>3. If sample insert failed → Check database permissions</p>";
echo "<p>4. If sample insert worked → Check admin panel now</p>";
?>