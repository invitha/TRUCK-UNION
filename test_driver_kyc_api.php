<?php
// Test the Driver KYC API to see what's causing the 500 error

header('Content-Type: application/json');

echo "🧪 **TESTING DRIVER KYC API**\n";
echo "Testing the submit_driver_kyc.php endpoint...\n\n";

// Test 1: Check if the API file exists
$api_file = 'server_php/api1_vendor/submit_driver_kyc.php';
if (file_exists($api_file)) {
    echo "✅ API file exists: $api_file\n";
} else {
    echo "❌ API file NOT found: $api_file\n";
    exit;
}

// Test 2: Test with sample JSON data
$test_data = json_encode([
    'firebase_uid' => 'test_driver_uid',
    'driver_name' => 'Test Driver',
    'driver_mobile' => '9876543210',
    'driver_email' => 'test@driver.com',
    'aadhar_number' => '123456789012',
    'pan_number' => 'ABCDE1234F',
    'license_number' => 'DL1234567890',
    'address' => 'Test Address',
    'city' => 'Test City',
    'state' => 'Test State',
    'pincode' => '123456'
]);

echo "📦 Test data: $test_data\n\n";

// Test 3: Simulate the API call
$url = 'https://crm.abra-logistic.com/api1/vendor/submit_driver_kyc.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $test_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($test_data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "🌐 **API TEST RESULTS:**\n";
echo "HTTP Status Code: $http_code\n";

if ($error) {
    echo "❌ CURL Error: $error\n";
} else {
    echo "📨 Response: $response\n";
}

echo "\n🔍 **ANALYSIS:**\n";
if ($http_code == 200) {
    echo "✅ API is working correctly!\n";
} elseif ($http_code == 500) {
    echo "❌ 500 Internal Server Error - Check server logs or PHP errors\n";
    echo "💡 Possible issues:\n";
    echo "   - Database connection problems\n";
    echo "   - PHP syntax errors\n";
    echo "   - Missing PHP extensions\n";
    echo "   - Server configuration issues\n";
} else {
    echo "⚠️ Unexpected HTTP status: $http_code\n";
}

echo "\n✨ Test completed!\n";
?>