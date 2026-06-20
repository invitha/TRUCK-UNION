<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    die('<div style="padding:20px;color:red;">Database connection failed</div>');
}
$con->set_charset('utf8mb4');

// Helper function to get address from coordinates
function getReadableAddress($latitude, $longitude, $storedAddress) {
    // If stored address exists and doesn't start with "GPS:", use it
    if (!empty($storedAddress) && strpos($storedAddress, 'GPS:') !== 0 && $storedAddress != 'Unknown location') {
        return $storedAddress;
    }
    
    // Otherwise, try to get address from OpenStreetMap
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TruckUnionApp/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['address'])) {
            $addr = $data['address'];
            $parts = [];
            
            // Build complete address with all details
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
    
    // Fallback to GPS coordinates
    return 'GPS: ' . number_format($latitude, 6) . ', ' . number_format($longitude, 6);
}

// Check if location columns exist
$columns_check = $con->query("SHOW COLUMNS FROM vehicles LIKE 'is_online'");
$has_location_columns = $columns_check->num_rows > 0;

// Get filter parameters
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$size_filter = isset($_GET['size_filter']) ? $_GET['size_filter'] : '';
$type_filter = isset($_GET['type_filter']) ? $_GET['type_filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
if ($has_location_columns) {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet,
                     driver_name, driver_username, status,
                     is_online, last_latitude, last_longitude, last_location_update, location_address,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
} else {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet,
                     driver_name, driver_username, status,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
}

// Apply filters
if ($status_filter == 'online' && $has_location_columns) {
    $query .= " AND is_online = 1";
} elseif ($status_filter == 'offline' && $has_location_columns) {
    $query .= " AND is_online = 0";
}

if (!empty($size_filter)) {
    $query .= " AND vehicle_size_feet = '" . $con->real_escape_string($size_filter) . "'";
}

if (!empty($type_filter)) {
    $query .= " AND vehicle_type = '" . $con->real_escape_string($type_filter) . "'";
}

if (!empty($search)) {
    $search_term = $con->real_escape_string($search);
    $query .= " AND (vehicle_number LIKE '%$search_term%' OR
                     driver_name LIKE '%$search_term%' OR
                     vendor_name LIKE '%$search_term%' OR
                     location_address LIKE '%$search_term%')";
}

if ($has_location_columns) {
    $query .= " ORDER BY is_online DESC, last_location_update DESC, created_at DESC";
} else {
    $query .= " ORDER BY created_at DESC";
}

$result = $con->query($query);

$total = 0;
$online = 0;
$offline = 0;
$vehicles = [];
$size_counts = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $total++;
        
        // Check if vehicle is truly online
        $vehicleOnline = false;
        if ($has_location_columns && $row['is_online'] == 1) {
            if (!empty($row['last_location_update'])) {
                $lastUpdateTime = strtotime($row['last_location_update']);
                $currentTime = time();
                $minutesSinceUpdate = ($currentTime - $lastUpdateTime) / 60;
                $vehicleOnline = ($minutesSinceUpdate <= 5);
            }
        }
        
        if ($vehicleOnline) {
            $online++;
        } else {
            $offline++;
        }
        
        // Count by size
        $size = $row['vehicle_size_feet'];
        if (!isset($size_counts[$size])) {
            $size_counts[$size] = 0;
        }
        $size_counts[$size]++;
        
        $vehicles[] = $row;
    }
}

// Group vehicles by size
$vehicles_by_size = [];
foreach ($vehicles as $v) {
    $size = $v['vehicle_size_feet'];
    if (!isset($vehicles_by_size[$size])) {
        $vehicles_by_size[$size] = [];
    }
    $vehicles_by_size[$size][] = $v;
}

// Sort sizes numerically
uksort($vehicles_by_size, function($a, $b) {
    return intval($a) - intval($b);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Dashboard - TRUCK UNION</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0D2E6E 0%, #1E40AF 100%);
    min-height: 100vh;
    padding: 20px;
}
.container { max-width: 1600px; margin: 0 auto; }
.header {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    margin-bottom: 25px;
}
.header h1 { color: #333; font-size: 28px; margin-bottom: 10px; }
.header p { color: #666; font-size: 14px; }
.refresh-note {
    background: #FEF3C7;
    color: #92400E;
    padding: 10px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
}
.filters-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.filter-group { display: flex; flex-direction: column; }
.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 5px;
}
.filter-group select,
.filter-group input {
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #0D2E6E;
}
.filter-buttons { display: flex; gap: 10px; margin-top: 15px; }
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: linear-gradient(135deg, #0D2E6E 0%, #1E40AF 100%);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(13, 46, 110, 0.3);
}
.btn-secondary { background: #f0f0f0; color: #333; }
.btn-secondary:hover { background: #e0e0e0; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-card h3 { font-size: 32px; margin-bottom: 8px; }
.stat-card p { color: #666; font-size: 13px; font-weight: 600; }
.stat-card.online h3 { color: #10B981; }
.stat-card.offline h3 { color: #EF4444; }
.stat-card.total h3 { color: #0D2E6E; }
.stat-card.size h3 { color: #8B5CF6; }
.section-header {
    background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
    color: white;
    padding: 15px 20px;
    font-size: 16px;
    font-weight: 700;
    border-radius: 10px;
    margin: 20px 0 10px 0;
    box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
}
.table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
    margin-bottom: 20px;
}
table { width: 100%; border-collapse: collapse; }
thead {
    background: linear-gradient(135deg, #0D2E6E 0%, #1E40AF 100%);
    color: white;
}
th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
}
td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}
tbody tr { transition: background 0.2s; }
tbody tr:hover { background: #f8f9ff; }
tbody tr.online-row { border-left: 4px solid #10B981; }
tbody tr.offline-row { border-left: 4px solid #EF4444; }
.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}
.status-online { background: #D1FAE5; color: #065F46; }
.status-offline { background: #FEE2E2; color: #991B1B; }
.location-link {
    color: #0D2E6E;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.location-link:hover { text-decoration: underline; }
.vehicle-type {
    background: #E0E7FF;
    color: #3730A3;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.last-update { font-size: 11px; color: #999; }
.no-data { text-align: center; padding: 40px; color: #999; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚛 Vehicle Dashboard</h1>
        <p>TRUCK UNION — Internal Team View</p>
    </div>

    <div class="refresh-note">
        ⏱️ Page auto-refreshes every 30 seconds | Last updated: <?php echo date('Y-m-d H:i:s'); ?>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <h3 style="margin-bottom: 10px; color: #333;">🔍 Filters & Search</h3>
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status_filter">
                        <option value="">All Status</option>
                        <option value="online" <?php echo ($status_filter == 'online') ? 'selected' : ''; ?>>Online Only</option>
                        <option value="offline" <?php echo ($status_filter == 'offline') ? 'selected' : ''; ?>>Offline Only</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Vehicle Size</label>
                    <select name="size_filter">
                        <option value="">All Sizes</option>
                        <option value="6 feet" <?php echo ($size_filter == '6 feet') ? 'selected' : ''; ?>>6 Feet</option>
                        <option value="7 feet" <?php echo ($size_filter == '7 feet') ? 'selected' : ''; ?>>7 Feet</option>
                        <option value="8 feet" <?php echo ($size_filter == '8 feet') ? 'selected' : ''; ?>>8 Feet</option>
                        <option value="10 feet" <?php echo ($size_filter == '10 feet') ? 'selected' : ''; ?>>10 Feet</option>
                        <option value="12 feet" <?php echo ($size_filter == '12 feet') ? 'selected' : ''; ?>>12 Feet</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Vehicle Type</label>
                    <select name="type_filter">
                        <option value="">All Types</option>
                        <option value="Light Truck" <?php echo ($type_filter == 'Light Truck') ? 'selected' : ''; ?>>Light Truck</option>
                        <option value="Medium Truck" <?php echo ($type_filter == 'Medium Truck') ? 'selected' : ''; ?>>Medium Truck</option>
                        <option value="Heavy Truck" <?php echo ($type_filter == 'Heavy Truck') ? 'selected' : ''; ?>>Heavy Truck</option>
                        <option value="Pickup" <?php echo ($type_filter == 'Pickup') ? 'selected' : ''; ?>>Pickup</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Vehicle, driver, vendor..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">Clear</button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3><?php echo $total; ?></h3>
            <p>Total Vehicles</p>
        </div>
        <div class="stat-card online">
            <h3><?php echo $online; ?></h3>
            <p>Online Now</p>
        </div>
        <div class="stat-card offline">
            <h3><?php echo $offline; ?></h3>
            <p>Offline</p>
        </div>
        <?php foreach ($size_counts as $size => $count): ?>
        <div class="stat-card size">
            <h3><?php echo $count; ?></h3>
            <p><?php echo htmlspecialchars($size); ?> Vehicles</p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Vehicles by Size -->
    <?php if (empty($vehicles)): ?>
    <div class="table-container">
        <div class="no-data">No vehicles found</div>
    </div>
    <?php else: ?>
        <?php foreach ($vehicles_by_size as $size => $size_vehicles): ?>
        <div class="section-header">
            📦 <?php echo htmlspecialchars($size); ?> Vehicles (<?php echo count($size_vehicles); ?>)
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Vehicle No.</th>
                        <th>Type</th>
                        <th>Driver</th>
                        <th>Vendor</th>
                        <th>Location</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($size_vehicles as $v): ?>
                    <?php
                    // Check online status
                    $isOnline = false;
                    if ($has_location_columns && $v['is_online'] == 1 && !empty($v['last_location_update'])) {
                        $mins = (time() - strtotime($v['last_location_update'])) / 60;
                        $isOnline = ($mins <= 5);
                    }
                    
                    $hasLocation = $has_location_columns && !empty($v['last_latitude']) && !empty($v['last_longitude']);
                    
                    if ($hasLocation) {
                        $locationText = getReadableAddress($v['last_latitude'], $v['last_longitude'], $v['location_address']);
                        $locationLink = 'https://www.google.com/maps?q=' . $v['last_latitude'] . ',' . $v['last_longitude'];
                    } else {
                        $locationText = 'No location';
                        $locationLink = '#';
                    }
                    
                    if ($has_location_columns && $v['last_location_update']) {
                        $mins = floor((time() - strtotime($v['last_location_update'])) / 60);
                        if ($mins < 1) $lastUpdate = 'Just now';
                        elseif ($mins < 60) $lastUpdate = $mins . ' min ago';
                        else $lastUpdate = date('Y-m-d H:i:s', strtotime($v['last_location_update']));
                    } else {
                        $lastUpdate = 'Never';
                    }
                    ?>
                    <tr class="<?php echo $isOnline ? 'online-row' : 'offline-row'; ?>">
                        <td>
                            <span class="status-badge <?php echo $isOnline ? 'status-online' : 'status-offline'; ?>">
                                <?php echo $isOnline ? '🟢 Online' : '🔴 Offline'; ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($v['vehicle_number']); ?></strong></td>
                        <td><span class="vehicle-type"><?php echo htmlspecialchars($v['vehicle_type']); ?></span></td>
                        <td><?php echo htmlspecialchars($v['driver_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($v['vendor_name']); ?><br>
                            <small><?php echo htmlspecialchars($v['vendor_phone']); ?></small>
                        </td>
                        <td>
                            <?php if ($hasLocation): ?>
                            <a href="<?php echo $locationLink; ?>" target="_blank" class="location-link">📍 View Map</a><br>
                            <small style="display: block; margin-top: 4px; line-height: 1.4;"><?php echo htmlspecialchars($locationText); ?></small>
                            <?php else: ?>
                            <span style="color: #999;">No location</span>
                            <?php endif; ?>
                        </td>
                        <td class="last-update"><?php echo $lastUpdate; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(function() {
    window.location.reload();
}, 30000);
</script>
</body>
</html>
<?php $con->close(); ?>
