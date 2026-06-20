<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);

// Database connection
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) die('DB Error');
$con->set_charset('utf8mb4');

// Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_vehicle') {
        $vehicle_id = intval($_POST['vehicle_id']);
        $al_number = trim($_POST['al_number']);
        $pickup_location = trim($_POST['pickup_location']);
        $delivery_location = trim($_POST['delivery_location']);
        $expected_completion_date = $_POST['expected_completion_date'];
        $assigned_by = trim($_POST['assigned_by']) ?: 'Internal Team';
        $payment_status = $_POST['payment_status'];
        $payment_amount = floatval($_POST['payment_amount']);
        $advance_amount = floatval($_POST['advance_amount']) ?: 0;
        $notes = trim($_POST['notes']) ?: '';
        
        // Get vehicle details
        $vehicle_query = "SELECT vendor_firebase_uid, vehicle_number, vehicle_name, driver_name, vendor_name, vendor_phone 
                         FROM vehicles WHERE id = ?";
        $stmt = $con->prepare($vehicle_query);
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();
        $vehicle_result = $stmt->get_result();
        $vehicle = $vehicle_result->fetch_assoc();
        
        if ($vehicle) {
            $insert_query = "INSERT INTO fleet_assignments 
                           (al_number, vehicle_id, vendor_firebase_uid, vehicle_number, vehicle_name, 
                            driver_name, assigned_by, pickup_location, delivery_location, 
                            expected_completion_date, status, notes, payment_status, payment_amount, advance_amount) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)";
            
            $stmt = $con->prepare($insert_query);
            $stmt->bind_param("sisssssssssddd", 
                $al_number, $vehicle_id, $vehicle['vendor_firebase_uid'], 
                $vehicle['vehicle_number'], $vehicle['vehicle_name'], $vehicle['driver_name'],
                $assigned_by, $pickup_location, $delivery_location, 
                $expected_completion_date, $notes, $payment_status, $payment_amount, $advance_amount
            );
            
            if ($stmt->execute()) {
                $success_message = "✅ Vehicle assigned successfully to AL: $al_number";
            } else {
                $error_message = "❌ Error: " . $stmt->error;
            }
        } else {
            $error_message = "❌ Vehicle not found";
        }
    }
}

// ── Get filter parameters ─────────────────────────────────
$f_search = trim($_GET['q']      ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_size   = trim($_GET['size']   ?? '');
$f_type   = trim($_GET['type']   ?? '');
$f_city   = trim($_GET['city']   ?? '');
$f_state  = trim($_GET['state']  ?? '');
$f_group  = trim($_GET['group']  ?? 'size');

// Build WHERE clause for database query
$where_parts = [];
if ($f_search) {
    $s = $con->real_escape_string($f_search);
    $where_parts[] = "(vehicle_number LIKE '%$s%' OR vehicle_name LIKE '%$s%' OR driver_name LIKE '%$s%' OR vendor_name LIKE '%$s%' OR driver_username LIKE '%$s%')";
}
if ($f_size) {
    $sz = $con->real_escape_string($f_size);
    $where_parts[] = "vehicle_size_feet = '$sz'";
}
if ($f_type) {
    $tp = $con->real_escape_string($f_type);
    $where_parts[] = "vehicle_type = '$tp'";
}
$where = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Helper function to get address from coordinates
function getReadableAddress($latitude, $longitude, $storedAddress) {
    if (!empty($storedAddress) && strpos($storedAddress, 'GPS:') !== 0 && $storedAddress != 'Unknown location') {
        return $storedAddress;
    }
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TruckUnionApp/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['address'])) {
            $addr = $data['address'];
            $parts = [];
            
            if (!empty($addr['house_number'])) $parts[] = $addr['house_number'];
            if (!empty($addr['road'])) $parts[] = $addr['road'];
            if (!empty($addr['neighbourhood'])) $parts[] = $addr['neighbourhood'];
            if (!empty($addr['suburb'])) $parts[] = $addr['suburb'];
            if (!empty($addr['city_district'])) $parts[] = $addr['city_district'];
            if (!empty($addr['city'])) $parts[] = $addr['city'];
            elseif (!empty($addr['town'])) $parts[] = $addr['town'];
            elseif (!empty($addr['village'])) $parts[] = $addr['village'];
            if (!empty($addr['state_district'])) $parts[] = $addr['state_district'];
            if (!empty($addr['state'])) $parts[] = $addr['state'];
            if (!empty($addr['postcode'])) $parts[] = $addr['postcode'];
            if (!empty($addr['country'])) $parts[] = $addr['country'];
            
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }
    }
    
    return 'GPS: ' . number_format($latitude, 6) . ', ' . number_format($longitude, 6);
}

// Check if location columns exist
$columns_check = $con->query("SHOW COLUMNS FROM vehicles LIKE 'is_online'");
$has_location_columns = $columns_check->num_rows > 0;

// Check if payment columns exist in fleet_assignments
$payment_columns_check = $con->query("SHOW COLUMNS FROM fleet_assignments LIKE 'payment_status'");
$has_payment_columns = $payment_columns_check->num_rows > 0;

// Build query based on available columns and filters
if ($has_location_columns) {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet, vehicle_tonnage,
                     driver_name, driver_username, status,
                     is_online, last_latitude, last_longitude, last_location_update, location_address,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
} else {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet, vehicle_tonnage,
                     driver_name, driver_username, status,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
}

// Apply filters
if ($f_status == 'online' && $has_location_columns) {
    $query .= " AND is_online = 1";
} elseif ($f_status == 'offline' && $has_location_columns) {
    $query .= " AND is_online = 0";
}

if (!empty($f_size)) {
    $query .= " AND vehicle_size_feet = '" . $con->real_escape_string($f_size) . "'";
}

if (!empty($f_type)) {
    $query .= " AND vehicle_type = '" . $con->real_escape_string($f_type) . "'";
}

if ($has_location_columns) {
    $query .= " ORDER BY is_online DESC, last_location_update DESC, created_at DESC";
} else {
    $query .= " ORDER BY created_at DESC";
}

$result = $con->query($query);

$total = 0;
$online_cnt = 0;
$offline_cnt = 0;
$all_vehicles = [];
$vehicles = [];
$sizes = [];
$types = [];
$cities = [];
$states = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['_online'] = false;
        if ($has_location_columns && $row['is_online'] == 1) {
            if (!empty($row['last_location_update'])) {
                $lastUpdateTime = strtotime($row['last_location_update']);
                $currentTime = time();
                $minutesSinceUpdate = ($currentTime - $lastUpdateTime) / 60;
                $row['_online'] = ($minutesSinceUpdate <= 5);
            }
        }
        
        $row['_address'] = 'No location';
        $row['_city'] = '';
        $row['_state'] = '';
        
        if ($has_location_columns && !empty($row['last_latitude']) && !empty($row['last_longitude'])) {
            $fullAddr = getReadableAddress($row['last_latitude'], $row['last_longitude'], $row['location_address']);
            $row['_address'] = $fullAddr;
            
            if (!empty($fullAddr) && strpos($fullAddr, 'GPS:') === false) {
                $parts = array_map('trim', explode(',', $fullAddr));
                $cnt = count($parts);
                if ($cnt >= 3) {
                    $row['_state'] = $parts[$cnt - 3];
                    $row['_city'] = $parts[$cnt - 4] ?? $parts[$cnt - 5] ?? '';
                }
            }
        }
        
        $all_vehicles[] = $row;
    }
    
    foreach ($all_vehicles as $row) {
        if ($f_search) {
            $search_lower = strtolower($f_search);
            $searchable = strtolower(
                $row['vehicle_number'] . ' ' .
                $row['vehicle_name'] . ' ' .
                $row['driver_name'] . ' ' .
                $row['driver_username'] . ' ' .
                $row['vendor_name'] . ' ' .
                $row['vendor_phone'] . ' ' .
                $row['_address'] . ' ' .
                $row['_city'] . ' ' .
                $row['_state']
            );
            if (stripos($searchable, $search_lower) === false) continue;
        }
        
        if ($f_city && stripos($row['_city'], $f_city) === false) continue;
        if ($f_state && stripos($row['_state'], $f_state) === false) continue;
        if ($f_status == 'online' && !$row['_online']) continue;
        if ($f_status == 'offline' && $row['_online']) continue;
        
        $total++;
        if ($row['_online']) {
            $online_cnt++;
        } else {
            $offline_cnt++;
        }
        
        $size = $row['vehicle_size_feet'];
        if (!isset($sizes[$size])) {
            $sizes[$size] = 0;
        }
        $sizes[$size]++;
        
        if (!empty($row['_city'])) {
            if (!isset($cities[$row['_city']])) $cities[$row['_city']] = 0;
            $cities[$row['_city']]++;
        }
        if (!empty($row['_state'])) {
            if (!isset($states[$row['_state']])) $states[$row['_state']] = 0;
            $states[$row['_state']]++;
        }
        
        $vehicles[] = $row;
    }
}

uksort($sizes, fn($a,$b) => intval($a) - intval($b));
asort($types); asort($cities); asort($states);

$all_sizes  = [];
$all_types  = [];
$r = $con->query("SELECT DISTINCT vehicle_size_feet FROM vehicles ORDER BY CAST(vehicle_size_feet AS UNSIGNED)");
while($rw=$r->fetch_row()) if($rw[0]) $all_sizes[] = $rw[0];
$r = $con->query("SELECT DISTINCT vehicle_type FROM vehicles ORDER BY vehicle_type");
while($rw=$r->fetch_row()) if($rw[0]) $all_types[] = $rw[0];

$grouped = [];
foreach ($vehicles as $v) {
    switch ($f_group) {
        case 'type':   $key = $v['vehicle_type']         ?: 'Unknown';   break;
        case 'status': $key = $v['_online'] ? 'Online'   : 'Offline';    break;
        case 'city':   $key = $v['_city']                ?: 'Unknown';   break;
        case 'state':  $key = $v['_state']               ?: 'Unknown';   break;
        default:       $key = $v['vehicle_size_feet']    ?: 'Unknown';   break;
    }
    $grouped[$key][] = $v;
}

if ($f_group === 'size') {
    uksort($grouped, fn($a,$b) => intval($a) - intval($b));
} else {
    ksort($grouped);
}

function timeAgo($ts) {
    if (empty($ts)) return 'Never';
    $m = floor((time() - strtotime($ts)) / 60);
    if ($m < 1)   return '<span style="color:#10b981;font-weight:700;">Just now</span>';
    if ($m < 60)  return '<span style="color:#f59e0b;font-weight:600;">'.$m.' min ago</span>';
    if ($m < 1440)return '<span style="color:#ef4444;">'.floor($m/60).' hr ago</span>';
    return '<span style="color:#94a3b8;">'.date('d M H:i', strtotime($ts)).'</span>';
}

$con->close();
?>
