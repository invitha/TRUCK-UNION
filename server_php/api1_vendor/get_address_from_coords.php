<?php
// Helper function to get address from coordinates using OpenStreetMap Nominatim API
function getAddressFromCoordinates($latitude, $longitude) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TruckUnionApp/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['address'])) {
            $addr = $data['address'];
            $parts = [];
            
            // Build address from available parts
            if (!empty($addr['road'])) $parts[] = $addr['road'];
            if (!empty($addr['suburb'])) $parts[] = $addr['suburb'];
            if (!empty($addr['city'])) $parts[] = $addr['city'];
            if (!empty($addr['state_district'])) $parts[] = $addr['state_district'];
            if (!empty($addr['state'])) $parts[] = $addr['state'];
            if (!empty($addr['postcode'])) $parts[] = $addr['postcode'];
            if (!empty($addr['country'])) $parts[] = $addr['country'];
            
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }
    }
    
    return null;
}

// Test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
    $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : 0;
    
    if ($lat && $lon) {
        $address = getAddressFromCoordinates($lat, $lon);
        echo json_encode([
            'status' => 'success',
            'latitude' => $lat,
            'longitude' => $lon,
            'address' => $address ?: 'Address not found'
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please provide lat and lon parameters'
        ]);
    }
}
