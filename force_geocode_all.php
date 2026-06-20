<?php
// Force geocode all vehicles with GPS coordinates
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) die('DB Error: ' . $con->connect_error);
$con->set_charset('utf8mb4');

echo "<h2>Force Geocoding All Vehicles</h2>";
echo "<pre>";

// Get all vehicles with GPS coordinates
$result = $con->query("SELECT id, vehicle_number, last_latitude, last_longitude, location_address FROM vehicles WHERE last_latitude IS NOT NULL AND last_longitude IS NOT NULL");

if (!$result) {
    die("Query failed: " . $con->error);
}

$count = 0;
while ($row = $result->fetch_assoc()) {
    $id  = $row['id'];
    $lat = $row['last_latitude'];
    $lng = $row['last_longitude'];
    $num = $row['vehicle_number'];
    
    echo "\n--- Vehicle #$id ($num) ---\n";
    echo "GPS: $lat, $lng\n";
    
    // Call Nominatim API
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT,      'TruckUnionApp/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT,        10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        
        $city = $state = $addr = '';
        
        if (isset($data['address'])) {
            $a = $data['address'];
            
            // Extract city
            if (!empty($a['city']))         $city  = $a['city'];
            elseif (!empty($a['town']))     $city  = $a['town'];
            elseif (!empty($a['village']))  $city  = $a['village'];
            elseif (!empty($a['suburb']))   $city  = $a['suburb'];
            elseif (!empty($a['county']))   $city  = $a['county'];
            
            // Extract state
            if (!empty($a['state']))        $state = $a['state'];
            
            // Build full address
            $parts = [];
            if (!empty($a['house_number']))  $parts[] = $a['house_number'];
            if (!empty($a['road']))          $parts[] = $a['road'];
            if (!empty($a['neighbourhood'])) $parts[] = $a['neighbourhood'];
            if (!empty($a['suburb']))        $parts[] = $a['suburb'];
            if (!empty($a['city_district'])) $parts[] = $a['city_district'];
            if (!empty($city))               $parts[] = $city;
            if (!empty($a['state_district']))$parts[] = $a['state_district'];
            if (!empty($state))              $parts[] = $state;
            if (!empty($a['postcode']))      $parts[] = $a['postcode'];
            if (!empty($a['country']))       $parts[] = $a['country'];
            
            if (!empty($parts)) {
                $addr = implode(', ', $parts);
            }
        }
        
        if (empty($addr) && !empty($data['display_name'])) {
            $addr = $data['display_name'];
        }
        
        if (!empty($addr)) {
            echo "Address: $addr\n";
            echo "City: $city\n";
            echo "State: $state\n";
            
            // Update database
            $ae = $con->real_escape_string($addr);
            $ce = $con->real_escape_string($city);
            $se = $con->real_escape_string($state);
            
            $updateSql = "UPDATE vehicles SET location_address='$ae', geocoded_city='$ce', geocoded_state='$se' WHERE id=$id";
            if ($con->query($updateSql)) {
                echo "✓ Updated in database\n";
                $count++;
            } else {
                echo "✗ Database update failed: " . $con->error . "\n";
            }
        } else {
            echo "✗ No address found\n";
        }
    } else {
        echo "✗ API call failed\n";
    }
    
    // Rate limit: wait 1.5 seconds between requests
    if ($count < $result->num_rows) {
        sleep(2);
    }
}

echo "\n\n=== SUMMARY ===\n";
echo "Total vehicles geocoded: $count\n";
echo "\nDone! Refresh the dashboard to see updated addresses.\n";
echo "</pre>";

$con->close();
?>
