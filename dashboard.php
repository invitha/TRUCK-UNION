<?php
// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Database connection
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die('<h1>Database Connection Error</h1><p>Could not connect to database. Please check your credentials.</p><p>Error: ' . htmlspecialchars($con->connect_error) . '</p>');
    }
    $con->set_charset('utf8mb4');
    
    // Check and add vehicle_tonnage column if missing
    $tonnage_col_check = $con->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_tonnage'");
    if ($tonnage_col_check && $tonnage_col_check->num_rows == 0) {
        // Add vehicle_tonnage column
        $con->query("ALTER TABLE vehicles ADD COLUMN vehicle_tonnage VARCHAR(50) AFTER vehicle_size_feet");
    }
    
} catch (Exception $e) {
    die('<h1>Database Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ── Handle vehicle assignment with payment tracking ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_vehicle') {
    // First check if fleet_assignments table exists
    $table_check = $con->query("SHOW TABLES LIKE 'fleet_assignments'");
    
    if ($table_check && $table_check->num_rows == 0) {
        // Table doesn't exist - create it
        $create_table_sql = "CREATE TABLE IF NOT EXISTS fleet_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            al_number VARCHAR(50) NOT NULL,
            vehicle_id INT NOT NULL,
            vendor_firebase_uid VARCHAR(255),
            vehicle_number VARCHAR(50),
            vehicle_name VARCHAR(100),
            driver_name VARCHAR(100),
            assigned_by VARCHAR(100),
            pickup_location TEXT,
            delivery_location TEXT,
            expected_completion_date DATE,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            notes TEXT,
            payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid',
            payment_amount DECIMAL(10,2) DEFAULT 0.00,
            advance_amount DECIMAL(10,2) DEFAULT 0.00,
            remaining_amount DECIMAL(10,2) DEFAULT 0.00,
            vendor_transaction_id VARCHAR(100) NULL,
            payment_date DATETIME NULL,
            payment_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_vehicle_id (vehicle_id),
            INDEX idx_vendor_uid (vendor_firebase_uid),
            INDEX idx_status (status),
            INDEX idx_al_number (al_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $con->query($create_table_sql);
    }
    
    $vehicle_id = intval($_POST['vehicle_id']);
    $payment_status = $_POST['payment_status'];
    $payment_amount = floatval($_POST['payment_amount']);
    $advance_amount = floatval($_POST['advance_amount']) ?: 0;
    $vendor_transaction_id = $con->real_escape_string($_POST['vendor_transaction_id'] ?? '');
    $notes = trim($_POST['notes']) ?: '';
    // Auto-generate AL number
    $al_number = 'AL' . date('Ymd') . str_pad($vehicle_id, 4, '0', STR_PAD_LEFT);
    $pickup_location = '';
    $delivery_location = '';
    $expected_completion_date = date('Y-m-d');
    $assigned_by = 'Internal Team';
    
    // Get vehicle details
    $vehicle_query = "SELECT firebase_uid as vendor_firebase_uid, vehicle_number, vehicle_name, driver_name, vendor_name, vendor_phone 
                      FROM vehicles WHERE id = ?";
    $stmt = $con->prepare($vehicle_query);
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $vehicle_result = $stmt->get_result();
    $vehicle = $vehicle_result->fetch_assoc();
    
    if ($vehicle) {
        // Calculate remaining amount
        $remaining_amount = $payment_amount - $advance_amount;
        
        // Check if payment columns exist
        $check_cols = $con->query("SHOW COLUMNS FROM fleet_assignments LIKE 'payment_status'");
        $has_payment_cols = $check_cols && $check_cols->num_rows > 0;
        
        // Ensure assignment_date has a default value if it exists
        @$con->query("ALTER TABLE fleet_assignments MODIFY assignment_date DATETIME DEFAULT CURRENT_TIMESTAMP");
        
        // Add vendor_transaction_id column if it doesn't exist
        @$con->query("ALTER TABLE fleet_assignments ADD COLUMN vendor_transaction_id VARCHAR(100) NULL AFTER remaining_amount");
        
        if ($has_payment_cols) {
            $insert_query = "INSERT INTO fleet_assignments 
                           (al_number, vehicle_id, vendor_firebase_uid, vehicle_number, vehicle_name, 
                            driver_name, assigned_by, pickup_location, delivery_location, 
                            expected_completion_date, status, notes, payment_status, payment_amount, advance_amount, remaining_amount, vendor_transaction_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)";
            
            $stmt = $con->prepare($insert_query);
            $stmt->bind_param("sissssssssssddds", 
                $al_number, $vehicle_id, $vehicle['vendor_firebase_uid'], 
                $vehicle['vehicle_number'], $vehicle['vehicle_name'], $vehicle['driver_name'],
                $assigned_by, $pickup_location, $delivery_location, 
                $expected_completion_date, $notes, $payment_status, $payment_amount, $advance_amount, $remaining_amount, $vendor_transaction_id
            );
        } else {
            // Fallback without payment columns
            $insert_query = "INSERT INTO fleet_assignments 
                           (al_number, vehicle_id, vendor_firebase_uid, vehicle_number, vehicle_name, 
                            driver_name, assigned_by, pickup_location, delivery_location, 
                            expected_completion_date, status, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
            
            $stmt = $con->prepare($insert_query);
            $stmt->bind_param("sisssssssss", 
                $al_number, $vehicle_id, $vehicle['vendor_firebase_uid'], 
                $vehicle['vehicle_number'], $vehicle['vehicle_name'], $vehicle['driver_name'],
                $assigned_by, $pickup_location, $delivery_location, 
                $expected_completion_date, $notes
            );
        }
        
        if ($stmt->execute()) {
            
            // 1. Look up the Vendor's Integer ID from the vendors table using vendor_name
            $v_name_val = $vehicle['vendor_name'];
            $v_uid_val = $vehicle['vendor_firebase_uid'];
            $vendor_id_int = '';
            $v_stmt = $con->prepare("SELECT vendor_id FROM vendors WHERE vendor_name = ? LIMIT 1");
            if ($v_stmt) {
                $v_stmt->bind_param("s", $v_name_val);
                $v_stmt->execute();
                $v_res = $v_stmt->get_result();
                if ($v_row = $v_res->fetch_assoc()) {
                    $vendor_id_int = $v_row['vendor_id'];
                }
                $v_stmt->close();
            }

            // 2. Look up the Driver's Integer ID from the vendor_drivers table
            $d_name_val = $vehicle['driver_name'];
            $driver_id_int = '';
            if (!empty($vendor_id_int)) {
                $d_stmt = $con->prepare("SELECT id FROM vendor_drivers WHERE vendor_id = ? AND driver_name = ? LIMIT 1");
                if ($d_stmt) {
                    $d_stmt->bind_param("is", $vendor_id_int, $d_name_val);
                    $d_stmt->execute();
                    $d_res = $d_stmt->get_result();
                    if ($d_row = $d_res->fetch_assoc()) {
                        $driver_id_int = $d_row['id'];
                    }
                    $d_stmt->close();
                }
            }

            // ── Write advance payment directly into vendor_payments so it appears in history ──
            if ($advance_amount > 0) {
                // Find all courier cids linked to this al_number
                $cid_res = $con->query("SELECT cid FROM courier WHERE assigned_vehicle = '" . $con->real_escape_string($al_number) . "'");
                if ($cid_res) {
                    $vp_tx    = !empty($vendor_transaction_id) ? $vendor_transaction_id : 'Dashboard Entry';
                    $vp_note  = 'Initial advance (Vehicle Assignment — Dashboard)';
                    $vp_by    = 'Dashboard';
                    while ($cid_row = $cid_res->fetch_assoc()) {
                        $vcid = intval($cid_row['cid']);
                        // Only insert if no entry with same transaction_id already exists for this cid
                        $dup = $con->query("SELECT id FROM vendor_payments WHERE cid=$vcid AND transaction_id='" . $con->real_escape_string($vp_tx) . "' LIMIT 1");
                        if ($dup && $dup->num_rows == 0) {
                            $vp_ins = $con->prepare("INSERT INTO vendor_payments (cid, amount, transaction_id, notes, paid_by, paid_at) VALUES (?,?,?,?,?,NOW())");
                            if ($vp_ins) {
                                $vp_ins->bind_param("idsss", $vcid, $advance_amount, $vp_tx, $vp_note, $vp_by);
                                $vp_ins->execute();
                                $vp_ins->close();
                            }
                        }
                    }
                }
            }

            // Fallback to names/uids if integers weren't found (prevents empty assignment)
            $v_id = urlencode(!empty($vendor_id_int) ? $vendor_id_int : $v_uid_val);
            $d_id = urlencode(!empty($driver_id_int) ? $driver_id_int : $d_name_val);
            $v_number = urlencode($vehicle['vehicle_number']);
            $amt = $payment_amount;

            // Redirect to AWB created page (pass AL number for DB link, v_number for UI display)
            header("Location: awb-created-view.php?assign_al=" . urlencode($al_number) . "&v_num={$v_number}&v_id={$v_id}&d_id={$d_id}&amt={$amt}");
            exit;
        } else {
            $error_message = "❌ Error: " . $stmt->error;
        }
    } else {
        $error_message = "❌ Vehicle not found";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "✅ Vehicle assigned successfully to AL: " . htmlspecialchars($_GET['al'] ?? '');
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
$has_location_columns = $columns_check && $columns_check->num_rows > 0;

// Check if tonnage column exists
$tonnage_check = $con->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_tonnage'");
$has_tonnage_column = $tonnage_check && $tonnage_check->num_rows > 0;

// Build query based on available columns and filters
if ($has_location_columns && $has_tonnage_column) {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet, vehicle_tonnage,
                     driver_name, driver_username, status,
                     is_online, last_latitude, last_longitude, last_location_update, location_address,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
} elseif ($has_location_columns && !$has_tonnage_column) {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet,
                     driver_name, driver_username, status,
                     is_online, last_latitude, last_longitude, last_location_update, location_address,
                     created_at, updated_at
              FROM vehicles WHERE 1=1";
} elseif (!$has_location_columns && $has_tonnage_column) {
    $query = "SELECT id, firebase_uid, vendor_name, vendor_email, vendor_phone,
                     vehicle_number, vehicle_name, vehicle_year, vehicle_type, vehicle_size_feet, vehicle_tonnage,
                     driver_name, driver_username, status,
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

// Don't apply search filter in SQL - we'll do it in PHP after geocoding
// This allows searching for city/state names that are extracted from addresses

if ($has_location_columns) {
    $query .= " ORDER BY is_online DESC, last_location_update DESC, created_at DESC";
} else {
    $query .= " ORDER BY created_at DESC";
}

$result = $con->query($query);

if (!$result) {
    die('<h1>Query Error</h1><p>Error executing query: ' . htmlspecialchars($con->error) . '</p><p>Query: ' . htmlspecialchars($query) . '</p>');
}

$total = 0;
$online_cnt = 0;
$offline_cnt = 0;
$all_vehicles = []; // Store ALL vehicles before filtering
$vehicles = [];
$sizes = [];
$types = [];
$cities = [];
$states = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Check if vehicle is truly online (is_online = 1 AND updated within 5 minutes)
        $row['_online'] = false;
        if ($has_location_columns && $row['is_online'] == 1) {
            if (!empty($row['last_location_update'])) {
                $lastUpdateTime = strtotime($row['last_location_update']);
                $currentTime = time();
                $minutesSinceUpdate = ($currentTime - $lastUpdateTime) / 60;
                $row['_online'] = ($minutesSinceUpdate <= 5);
            }
        }
        
        // Get readable address
        $row['_address'] = 'No location';
        $row['_city'] = '';
        $row['_state'] = '';
        
        if ($has_location_columns && !empty($row['last_latitude']) && !empty($row['last_longitude'])) {
            $fullAddr = getReadableAddress($row['last_latitude'], $row['last_longitude'], $row['location_address']);
            $row['_address'] = $fullAddr;
            
            // Extract city and state from address
            if (!empty($fullAddr) && strpos($fullAddr, 'GPS:') === false) {
                $parts = array_map('trim', explode(',', $fullAddr));
                $cnt = count($parts);
                // City is usually 3-4 positions from end, State is 2-3 positions from end
                if ($cnt >= 3) {
                    $row['_state'] = $parts[$cnt - 3]; // State
                    $row['_city'] = $parts[$cnt - 4] ?? $parts[$cnt - 5] ?? '';
                }
            }
        }
        
        $all_vehicles[] = $row;
    }
    
    // Now filter the vehicles based on search and filters
    foreach ($all_vehicles as $row) {
        // Apply search filter FIRST - search in ALL fields including geocoded address
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
        
        // Apply city/state filters
        if ($f_city && stripos($row['_city'], $f_city) === false) continue;
        if ($f_state && stripos($row['_state'], $f_state) === false) continue;
        if ($f_status == 'online' && !$row['_online']) continue;
        if ($f_status == 'offline' && $row['_online']) continue;
        
        // Count stats for filtered vehicles
        $total++;
        if ($row['_online']) {
            $online_cnt++;
        } else {
            $offline_cnt++;
        }
        
        // Count by size
        $size = $row['vehicle_size_feet'];
        if (!isset($sizes[$size])) {
            $sizes[$size] = 0;
        }
        $sizes[$size]++;
        
        // Collect cities and states for filters
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

// Sort size keys numerically
uksort($sizes, fn($a,$b) => intval($a) - intval($b));
asort($types); asort($cities); asort($states);

// ── Get all filter options from DB ────────────────────────
$all_sizes  = [];
$all_types  = [];
$r = $con->query("SELECT DISTINCT vehicle_size_feet FROM vehicles ORDER BY CAST(vehicle_size_feet AS UNSIGNED)");
while($rw=$r->fetch_row()) if($rw[0]) $all_sizes[] = $rw[0];
$r = $con->query("SELECT DISTINCT vehicle_type FROM vehicles ORDER BY vehicle_type");
while($rw=$r->fetch_row()) if($rw[0]) $all_types[] = $rw[0];

// ── Group vehicles ────────────────────────────────────────
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

// Sort groups
if ($f_group === 'size') {
    uksort($grouped, fn($a,$b) => intval($a) - intval($b));
} else {
    ksort($grouped);
}

// Time ago helper
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Dashboard — TRUCK UNION</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --navy:   #0d1f3c;
  --navy-2: #153060;
  --navy-3: #2563c4;
  --navy-4: #4a8ae8;
  --navy-5: #dce9fc;
  --green:  #10b981;
  --red:    #ef4444;
  --amber:  #f59e0b;
  --purple: #8b5cf6;
  --bg:     #f0f4f8;
  --white:  #ffffff;
  --border: #e2e8f0;
  --text:   #1e293b;
  --muted:  #64748b;
  --font:   'Outfit', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ── TOP BAR ── */
.topbar {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-2) 100%);
  padding: 16px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
  box-shadow: 0 4px 20px rgba(0,0,0,0.25);
}
.topbar-left { display: flex; align-items: center; gap: 14px; }
.topbar-icon {
  width: 46px; height: 46px;
  background: rgba(255,255,255,0.12);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
}
.topbar-title { font-size: 20px; font-weight: 800; color: #fff; }
.topbar-sub   { font-size: 12px; color: rgba(255,255,255,0.5); }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.refresh-badge {
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.75);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}
.refresh-badge span { color: var(--amber); font-weight: 700; }
.btn-refresh {
  background: var(--navy-3);
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: var(--font);
  transition: all .2s;
}
.btn-refresh:hover { background: var(--navy-4); transform: translateY(-1px); }

/* ── MAIN LAYOUT ── */
.main { display: flex; gap: 0; min-height: calc(100vh - 78px); }

/* ── SIDEBAR FILTERS ── */
.sidebar {
  width: 260px;
  flex-shrink: 0;
  background: var(--white);
  border-right: 1px solid var(--border);
  padding: 20px 16px;
  overflow-y: auto;
  position: sticky;
  top: 78px;
  height: calc(100vh - 78px);
}
.sidebar-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--muted);
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}
.filter-section { margin-bottom: 24px; }
.filter-label {
  font-size: 12px; font-weight: 700;
  color: var(--navy); margin-bottom: 8px;
  display: block;
}

/* Search box */
.search-wrap { position: relative; margin-bottom: 20px; }
.search-wrap input {
  width: 100%;
  padding: 10px 10px 10px 36px;
  border: 2px solid var(--border);
  border-radius: 10px;
  font-size: 13px;
  font-family: var(--font);
  outline: none;
  transition: border-color .2s;
  color: var(--text);
}
.search-wrap input:focus { border-color: var(--navy-3); }
.search-wrap i {
  position: absolute; 
  left: 11px; 
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted); 
  font-size: 14px;
  pointer-events: none;
  z-index: 1;
}

/* Filter chips */
.chip-group { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
  padding: 5px 12px;
  border-radius: 20px;
  border: 1.5px solid var(--border);
  font-size: 12px; font-weight: 600;
  color: var(--muted);
  background: #f8fafc;
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
  display: inline-block;
}
.chip:hover  { border-color: var(--navy-3); color: var(--navy-3); background: var(--navy-5); }
.chip.active { border-color: var(--navy-3); background: var(--navy-3); color: #fff; }
.chip.green.active  { border-color: var(--green);  background: var(--green);  color: #fff; }
.chip.red.active    { border-color: var(--red);    background: var(--red);    color: #fff; }

/* Group by buttons */
.group-btn {
  display: block; width: 100%;
  padding: 9px 12px;
  border-radius: 8px;
  border: 1.5px solid var(--border);
  font-size: 13px; font-weight: 600;
  color: var(--muted);
  background: #f8fafc;
  cursor: pointer;
  text-align: left;
  text-decoration: none;
  margin-bottom: 6px;
  transition: all .15s;
}
.group-btn:hover  { border-color: var(--navy-3); color: var(--navy-3); }
.group-btn.active { border-color: var(--navy-3); background: var(--navy-5); color: var(--navy); }
.group-btn i { margin-right: 8px; width: 14px; }

.clear-link {
  display: block; text-align: center;
  font-size: 12px; color: var(--red);
  font-weight: 600; text-decoration: none;
  margin-top: 8px; padding: 6px;
  border-radius: 6px; transition: background .15s;
}
.clear-link:hover { background: #fee2e2; }

/* ── CONTENT ── */
.content { flex: 1; padding: 24px; overflow: hidden; }

/* Stats row */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.stat-box {
  background: var(--white);
  border-radius: 14px;
  padding: 18px 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  border-left: 4px solid transparent;
  display: flex; align-items: center; gap: 12px;
}
.stat-box.s-total  { border-color: var(--navy-3); }
.stat-box.s-online { border-color: var(--green);  }
.stat-box.s-offline{ border-color: var(--red);    }
.stat-box.s-size   { border-color: var(--purple);  }
.stat-icon {
  width: 40px; height: 40px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.s-total  .stat-icon { background: #dce9fc; color: var(--navy-3); }
.s-online .stat-icon { background: #d1fae5; color: var(--green);  }
.s-offline.stat-icon { background: #fee2e2; color: var(--red);    }
.s-size   .stat-icon { background: #ede9fe; color: var(--purple);  }
.stat-num  { font-size: 26px; font-weight: 800; line-height: 1; }
.stat-label{ font-size: 11px; font-weight: 600; color: var(--muted); margin-top: 2px; }

/* Group section */
.group-section { margin-bottom: 28px; }
.group-header {
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-2) 100%);
  color: #fff;
  padding: 14px 20px;
  border-radius: 12px 12px 0 0;
  font-size: 15px; font-weight: 700;
}
.group-header .gh-left { display: flex; align-items: center; gap: 10px; }
.group-badge {
  background: rgba(255,255,255,0.18);
  padding: 3px 12px; border-radius: 20px;
  font-size: 12px; font-weight: 700;
}
.group-pills { display: flex; gap: 8px; }
.gpill {
  padding: 3px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
}
.gpill-green  { background: #d1fae5; color: #065f46; }
.gpill-red    { background: #fee2e2; color: #991b1b; }

/* Table */
.vehicle-table-wrap {
  background: var(--white);
  border-radius: 0 0 12px 12px;
  overflow-x: auto;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
table { width: 100%; border-collapse: collapse; min-width: 700px; }
thead { background: #f8fafc; border-bottom: 2px solid var(--border); }
th {
  padding: 12px 16px;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px;
  color: var(--muted); text-align: left;
}
td {
  padding: 14px 16px;
  font-size: 13px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tbody tr { transition: background .12s; }
tbody tr:hover { background: #f8faff; }
tbody tr.is-online  { border-left: 3px solid var(--green); }
tbody tr.is-offline { border-left: 3px solid #e2e8f0; }

/* Badges */
.badge {
  display: inline-block; padding: 4px 11px;
  border-radius: 20px; font-size: 11px; font-weight: 700;
}
.badge-online  { background: #d1fae5; color: #065f46; }
.badge-offline { background: #f1f5f9; color: #64748b; }
.badge-type    { background: #e0e7ff; color: #3730a3; }
.badge-size    { background: #fef3c7; color: #92400e; }

.vehicle-num { font-weight: 800; color: var(--navy); font-size: 14px; }
.driver-name { font-weight: 600; color: var(--text); }
.vendor-name { font-size: 12px; color: var(--muted); }
.vendor-phone{ font-size: 11px; color: var(--muted); }

.loc-link {
  display: inline-flex; align-items: center; gap: 4px;
  color: var(--navy-3); font-weight: 600; font-size: 12px;
  text-decoration: none;
}
.loc-link:hover { text-decoration: underline; }
.loc-addr-wrap { margin-top: 4px; }
.loc-addr-short,
.loc-addr-full {
  font-size: 11px;
  color: var(--muted);
  max-width: 220px;
  line-height: 1.5;
  display: block;
  word-break: break-word;
}
.read-more-btn {
  font-size: 10px;
  font-weight: 700;
  color: var(--navy-3);
  cursor: pointer;
  margin-top: 2px;
  display: inline-block;
  transition: color .15s;
  background: var(--navy-5);
  border: none;
  padding: 4px 8px;
  border-radius: 4px;
}
.read-more-btn:hover { background: var(--navy-3); color: white; }
.no-loc { color: #cbd5e1; font-size: 12px; }

/* Assign Button */
.btn-assign {
  background: linear-gradient(135deg, var(--navy-3) 0%, var(--navy-4) 100%);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  font-family: var(--font);
  transition: all .2s;
  white-space: nowrap;
}
.btn-assign:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 196, 0.4);
}

/* Assignment Modal */
.assign-modal {
  display: none;
  position: fixed;
  z-index: 2000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.7);
  animation: fadeIn 0.2s;
  overflow-y: auto;
}
.assign-modal-content {
  background: white;
  margin: 40px auto;
  padding: 0;
  border-radius: 20px;
  max-width: 700px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  animation: slideDown 0.3s;
}
.assign-modal-header {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-2) 100%);
  color: white;
  padding: 24px 30px;
  border-radius: 20px 20px 0 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.assign-modal-header h3 {
  font-size: 22px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}
.assign-modal-close {
  background: rgba(255,255,255,0.2);
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 24px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.assign-modal-close:hover {
  background: var(--red);
  transform: rotate(90deg);
}
.assign-modal-body {
  padding: 30px;
}
.vehicle-info-box {
  background: var(--navy-5);
  border-left: 4px solid var(--navy-3);
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 24px;
}
.vehicle-info-box h4 {
  color: var(--navy);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 8px;
}
.vehicle-info-box p {
  color: var(--muted);
  font-size: 13px;
  margin: 4px 0;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
.form-group-modal {
  margin-bottom: 16px;
}
.form-group-modal label {
  display: block;
  color: var(--navy);
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 8px;
}
.form-group-modal input,
.form-group-modal select,
.form-group-modal textarea {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--border);
  border-radius: 8px;
  font-size: 14px;
  font-family: var(--font);
  transition: all .2s;
  color: var(--text);
}
.form-group-modal input:focus,
.form-group-modal select:focus,
.form-group-modal textarea:focus {
  outline: none;
  border-color: var(--navy-3);
  box-shadow: 0 0 0 3px rgba(37, 99, 196, 0.1);
}
.form-group-modal textarea {
  resize: vertical;
  min-height: 80px;
}
.payment-section {
  background: #fef3c7;
  border-left: 4px solid var(--amber);
  padding: 20px;
  border-radius: 8px;
  margin: 20px 0;
}
.payment-section h4 {
  color: #92400e;
  font-size: 15px;
  font-weight: 800;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-submit-assign {
  background: linear-gradient(135deg, var(--green) 0%, #059669 100%);
  color: white;
  border: none;
  padding: 14px 28px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 800;
  cursor: pointer;
  width: 100%;
  font-family: var(--font);
  transition: all .2s;
  margin-top: 20px;
}
.btn-submit-assign:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}
.alert-box {
  padding: 14px 18px;
  border-radius: 10px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 600;
  animation: slideDown 0.3s;
}
.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 2px solid var(--green);
}
.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 2px solid var(--red);
}

/* Address Modal */
.address-modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.6);
  animation: fadeIn 0.2s;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.address-modal-content {
  background: white;
  margin: 10% auto;
  padding: 30px;
  border-radius: 16px;
  max-width: 600px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  animation: slideDown 0.3s;
}
@keyframes slideDown {
  from { transform: translateY(-50px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
.address-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--border);
}
.address-modal-header h3 {
  color: var(--navy);
  font-size: 20px;
  font-weight: 700;
}
.address-modal-close {
  background: var(--red);
  color: white;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s;
}
.address-modal-close:hover {
  background: #dc2626;
  transform: rotate(90deg);
}
.address-modal-body {
  font-size: 15px;
  line-height: 1.8;
  color: var(--text);
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 4px solid var(--navy-3);
}

.empty-state {
  text-align: center; padding: 48px 20px;
  background: var(--white); border-radius: 0 0 12px 12px;
}
.empty-state .es-icon { font-size: 48px; margin-bottom: 12px; }
.empty-state p { color: var(--muted); font-size: 14px; }

/* No results at all */
.no-results {
  background: var(--white); border-radius: 14px;
  padding: 64px 32px; text-align: center;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.no-results .nr-icon { font-size: 56px; margin-bottom: 16px; }
.no-results h3 { font-size: 20px; font-weight: 700; color: var(--navy); margin-bottom: 8px; }
.no-results p  { color: var(--muted); font-size: 14px; }

/* ── Responsive ── */
@media(max-width:768px) {
  .sidebar { display: none; }
  .topbar-title { font-size: 16px; }
  .stats-row { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ══ TOP BAR ══ -->
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-icon">🚛</div>
    <div>
      <div class="topbar-title">Vehicle Dashboard</div>
      <div class="topbar-sub">TRUCK UNION — Internal Team View</div>
    </div>
  </div>
  <div class="topbar-right">
    <a href="../manage_vendor_assignments.php" style="background:rgba(255,255,255,0.15);color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;border:1px solid rgba(255,255,255,0.25);">📋 Vendor Payments</a>
    <div class="refresh-badge">Auto-refresh in <span id="countdown">30</span>s</div>
    <button class="btn-refresh" onclick="location.reload()">↻ Refresh</button>
  </div>
</div>

<?php if (isset($success_message)): ?>
<div style="background:#d1fae5;border-left:4px solid #10b981;padding:16px 28px;color:#065f46;font-weight:600;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
  <?php echo $success_message; ?>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div style="background:#fee2e2;border-left:4px solid #ef4444;padding:16px 28px;color:#991b1b;font-weight:600;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
  <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="main">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">
    <form method="GET" id="filterForm">
      <!-- Search -->
      <div class="search-wrap">
        <i>🔍</i>
        <input type="text" name="q" id="searchInput" placeholder="Vehicle, driver, vendor..."
               value="<?php echo htmlspecialchars($f_search); ?>" autocomplete="off">
      </div>
      <input type="hidden" name="group"  value="<?php echo htmlspecialchars($f_group); ?>">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($f_status); ?>">
      <input type="hidden" name="size"   value="<?php echo htmlspecialchars($f_size); ?>">
      <input type="hidden" name="type"   value="<?php echo htmlspecialchars($f_type); ?>">
      <input type="hidden" name="city"   value="<?php echo htmlspecialchars($f_city); ?>">
      <input type="hidden" name="state"  value="<?php echo htmlspecialchars($f_state); ?>">
    </form>

    <!-- Status filter -->
    <div class="filter-section">
      <div class="sidebar-title">Status</div>
      <div class="chip-group">
        <?php
        $params = array_filter(['group'=>$f_group,'size'=>$f_size,'type'=>$f_type,'city'=>$f_city,'state'=>$f_state,'q'=>$f_search]);
        function qs($extra=[],$params=[]) { return '?'.http_build_query(array_merge($params,$extra)); }
        ?>
        <a href="<?php echo qs(['status'=>''],       $params); ?>" class="chip <?php echo !$f_status   ? 'active' : ''; ?>">All</a>
        <a href="<?php echo qs(['status'=>'online'],  $params); ?>" class="chip green <?php echo $f_status=='online'  ? 'active' : ''; ?>">🟢 Online</a>
        <a href="<?php echo qs(['status'=>'offline'], $params); ?>" class="chip red   <?php echo $f_status=='offline' ? 'active' : ''; ?>">🔴 Offline</a>
      </div>
    </div>

    <!-- Size filter -->
    <div class="filter-section">
      <div class="sidebar-title">Vehicle Size</div>
      <div class="chip-group">
        <a href="<?php echo qs(['size'=>''], $params); ?>" class="chip <?php echo !$f_size ? 'active' : ''; ?>">All</a>
        <?php foreach ($all_sizes as $sz): ?>
        <a href="<?php echo qs(['size'=>$sz], $params); ?>" class="chip <?php echo $f_size==$sz ? 'active' : ''; ?>"><?php echo htmlspecialchars($sz); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Type filter -->
    <div class="filter-section">
      <div class="sidebar-title">Vehicle Type</div>
      <div class="chip-group">
        <a href="<?php echo qs(['type'=>''], $params); ?>" class="chip <?php echo !$f_type ? 'active' : ''; ?>">All</a>
        <?php foreach ($all_types as $tp): ?>
        <a href="<?php echo qs(['type'=>$tp], $params); ?>" class="chip <?php echo $f_type==$tp ? 'active' : ''; ?>"><?php echo htmlspecialchars($tp); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- City filter (from live data) -->
    <?php if (!empty($cities)): ?>
    <div class="filter-section">
      <div class="sidebar-title">City</div>
      <div class="chip-group">
        <a href="<?php echo qs(['city'=>''], $params); ?>" class="chip <?php echo !$f_city ? 'active' : ''; ?>">All</a>
        <?php foreach ($cities as $city => $cnt): ?>
        <a href="<?php echo qs(['city'=>$city], $params); ?>" class="chip <?php echo $f_city==$city ? 'active' : ''; ?>"><?php echo htmlspecialchars($city); ?> <small>(<?php echo $cnt; ?>)</small></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- State filter -->
    <?php if (!empty($states)): ?>
    <div class="filter-section">
      <div class="sidebar-title">State</div>
      <div class="chip-group">
        <a href="<?php echo qs(['state'=>''], $params); ?>" class="chip <?php echo !$f_state ? 'active' : ''; ?>">All</a>
        <?php foreach ($states as $state => $cnt): ?>
        <a href="<?php echo qs(['state'=>$state], $params); ?>" class="chip <?php echo $f_state==$state ? 'active' : ''; ?>"><?php echo htmlspecialchars($state); ?> <small>(<?php echo $cnt; ?>)</small></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Group by -->
    <div class="filter-section">
      <div class="sidebar-title">Group By</div>
      <?php
      $gp = array_filter(['status'=>$f_status,'size'=>$f_size,'type'=>$f_type,'city'=>$f_city,'state'=>$f_state,'q'=>$f_search]);
      $groups = ['size'=>'📦 Vehicle Size','type'=>'🚛 Vehicle Type','status'=>'📡 Online Status','city'=>'🏙️ City','state'=>'🗺️ State','vendor'=>'👤 Vendor'];
      foreach ($groups as $gval => $glabel): ?>
      <a href="<?php echo qs(['group'=>$gval], $gp); ?>" class="group-btn <?php echo $f_group==$gval ? 'active' : ''; ?>"><?php echo $glabel; ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($f_status || $f_size || $f_type || $f_city || $f_state || $f_search): ?>
    <a href="?group=<?php echo $f_group; ?>" class="clear-link">✕ Clear all filters</a>
    <?php endif; ?>
    
    <!-- View Toggle -->
    <div class="filter-section" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border);">
      <div class="sidebar-title">View Mode</div>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['view'=>'summary'])); ?>" 
         class="group-btn <?php echo (isset($_GET['view']) && $_GET['view']=='summary') ? 'active' : ''; ?>">
         📊 Vendor Summary
      </a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['view'=>'detailed'])); ?>" 
         class="group-btn <?php echo (!isset($_GET['view']) || $_GET['view']=='detailed') ? 'active' : ''; ?>">
         📋 Detailed View
      </a>
    </div>
  </aside>

  <!-- ══ CONTENT ══ -->
  <div class="content">

    <!-- Stats row -->
    <div class="stats-row">
      <div class="stat-box s-total">
        <div class="stat-icon">🚛</div>
        <div><div class="stat-num"><?php echo $total; ?></div><div class="stat-label">Total Vehicles</div></div>
      </div>
      <div class="stat-box s-online">
        <div class="stat-icon">🟢</div>
        <div><div class="stat-num" style="color:var(--green)"><?php echo $online_cnt; ?></div><div class="stat-label">Online Now</div></div>
      </div>
      <div class="stat-box s-offline">
        <div class="stat-icon">🔴</div>
        <div><div class="stat-num" style="color:var(--red)"><?php echo $offline_cnt; ?></div><div class="stat-label">Offline</div></div>
      </div>
      <?php foreach ($sizes as $sz => $cnt): ?>
      <div class="stat-box s-size">
        <div class="stat-icon" style="background:#ede9fe;color:var(--purple);">📦</div>
        <div><div class="stat-num" style="color:var(--purple)"><?php echo $cnt; ?></div><div class="stat-label"><?php echo htmlspecialchars($sz); ?> Vehicles</div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Grouped vehicle tables -->
    <?php if (empty($vehicles)): ?>
    <div class="no-results">
      <div class="nr-icon">🚫</div>
      <h3>No vehicles found</h3>
      <p>Try adjusting your filters or search query.</p>
      <a href="?" style="color:var(--navy-3);font-weight:600;margin-top:12px;display:inline-block;">Clear all filters →</a>
    </div>
    <?php elseif (isset($_GET['view']) && $_GET['view'] == 'summary'): ?>
    
    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VENDOR SUMMARY VIEW - Excel-like Analytics Table -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php
    // Build vendor summary data
    $vendor_summary = [];
    $fleet_sizes = ['6 feet', '7 feet', '8 feet', '10 feet', '12 feet'];
    $fleet_tonnages = ['1 Ton', '2 Ton', '3 Ton', '5 Ton', '7 Ton', '10 Ton', '12 Ton', '15 Ton', '20 Ton', '25 Ton', '30 Ton'];
    
    foreach ($vehicles as $v) {
        $vendor_key = $v['vendor_name'] . '|' . $v['vendor_phone'];
        
        if (!isset($vendor_summary[$vendor_key])) {
            $vendor_summary[$vendor_key] = [
                'name' => $v['vendor_name'],
                'phone' => $v['vendor_phone'],
                'state' => $v['_state'] ?: 'Unknown',
                'locations' => [],
                'fleet_by_size' => [],
                'fleet_by_tonnage' => [],
                'total' => 0,
                'total_online' => 0,
                'total_offline' => 0
            ];
            
            // Initialize fleet sizes
            foreach ($fleet_sizes as $size) {
                $vendor_summary[$vendor_key]['fleet_by_size'][$size] = ['online' => 0, 'offline' => 0];
            }
            
            // Initialize fleet tonnages
            foreach ($fleet_tonnages as $tonnage) {
                $vendor_summary[$vendor_key]['fleet_by_tonnage'][$tonnage] = ['online' => 0, 'offline' => 0];
            }
        }
        
        // Add location
        if (!empty($v['_city'])) {
            $vendor_summary[$vendor_key]['locations'][$v['_city']] = true;
        }
        
        // Count fleet by size
        $size = $v['vehicle_size_feet'];
        if (!isset($vendor_summary[$vendor_key]['fleet_by_size'][$size])) {
            $vendor_summary[$vendor_key]['fleet_by_size'][$size] = ['online' => 0, 'offline' => 0];
        }
        
        if ($v['_online']) {
            $vendor_summary[$vendor_key]['fleet_by_size'][$size]['online']++;
            $vendor_summary[$vendor_key]['total_online']++;
        } else {
            $vendor_summary[$vendor_key]['fleet_by_size'][$size]['offline']++;
            $vendor_summary[$vendor_key]['total_offline']++;
        }
        
        // Count fleet by tonnage (only if column exists)
        if ($has_tonnage_column) {
            $tonnage = $v['vehicle_tonnage'] ?: 'Unknown';
            if (!isset($vendor_summary[$vendor_key]['fleet_by_tonnage'][$tonnage])) {
                $vendor_summary[$vendor_key]['fleet_by_tonnage'][$tonnage] = ['online' => 0, 'offline' => 0];
            }
            
            if ($v['_online']) {
                $vendor_summary[$vendor_key]['fleet_by_tonnage'][$tonnage]['online']++;
            } else {
                $vendor_summary[$vendor_key]['fleet_by_tonnage'][$tonnage]['offline']++;
            }
        }
        
        $vendor_summary[$vendor_key]['total']++;
    }
    
    // Group by state
    $by_state = [];
    foreach ($vendor_summary as $data) {
        $state = $data['state'];
        if (!isset($by_state[$state])) $by_state[$state] = [];
        $by_state[$state][] = $data;
    }
    ksort($by_state);
    ?>
    
    <div style="background:white;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.06);overflow-x:auto;margin-bottom:24px;">
        <h2 style="color:var(--navy);font-size:22px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <span>�</span> Fleet Summary by Vehicle Size
        </h2>
        
        <table style="width:100%;border-collapse:collapse;min-width:1200px;font-size:12px;">
            <thead>
                <tr style="background:linear-gradient(135deg,var(--navy) 0%,var(--navy-2) 100%);color:white;">
                    <th style="padding:12px 10px;text-align:left;font-weight:700;position:sticky;left:0;background:var(--navy);z-index:2;">STATE</th>
                    <th style="padding:12px 10px;text-align:left;font-weight:700;min-width:180px;">VENDOR NAME</th>
                    <th style="padding:12px 10px;text-align:left;font-weight:700;">MOBILE</th>
                    <th style="padding:12px 10px;text-align:left;font-weight:700;min-width:200px;">SERVICE LOCATIONS</th>
                    <?php foreach ($fleet_sizes as $size): ?>
                    <th colspan="2" style="padding:12px 10px;text-align:center;font-weight:700;border-left:1px solid rgba(255,255,255,0.2);">
                        <?php echo htmlspecialchars($size); ?>
                    </th>
                    <?php endforeach; ?>
                    <th style="padding:12px 10px;text-align:center;font-weight:700;border-left:2px solid rgba(255,255,255,0.4);">TOTAL</th>
                </tr>
                <tr style="background:var(--navy-2);color:white;font-size:10px;">
                    <th colspan="4"></th>
                    <?php foreach ($fleet_sizes as $size): ?>
                    <th style="padding:6px 5px;text-align:center;border-left:1px solid rgba(255,255,255,0.2);background:#d1fae5;color:#065f46;">ON</th>
                    <th style="padding:6px 5px;text-align:center;background:#fee2e2;color:#991b1b;">OFF</th>
                    <?php endforeach; ?>
                    <th style="padding:6px 5px;text-align:center;border-left:2px solid rgba(255,255,255,0.4);"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($by_state as $state => $vendors): ?>
                    <?php foreach ($vendors as $idx => $vendor): ?>
                    <tr style="border-bottom:1px solid #f0f0f0;<?php echo $idx === 0 ? 'border-top:3px solid var(--purple);' : ''; ?>">
                        <?php if ($idx === 0): ?>
                        <td rowspan="<?php echo count($vendors); ?>" 
                            style="padding:12px 10px;font-weight:800;color:var(--navy);background:#f8fafc;border-right:2px solid var(--border);position:sticky;left:0;z-index:1;vertical-align:top;">
                            <?php echo htmlspecialchars($state); ?>
                        </td>
                        <?php endif; ?>
                        <td style="padding:12px 10px;font-weight:600;color:var(--text);">
                            <?php echo htmlspecialchars($vendor['name']); ?>
                        </td>
                        <td style="padding:12px 10px;color:var(--muted);font-size:11px;">
                            <?php echo htmlspecialchars($vendor['phone']); ?>
                        </td>
                        <td style="padding:12px 10px;color:var(--muted);font-size:11px;line-height:1.6;">
                            <?php 
                            $locs = array_keys($vendor['locations']);
                            echo !empty($locs) ? htmlspecialchars(implode(', ', array_slice($locs, 0, 3))) : '-';
                            if (count($locs) > 3) echo ' <span style="color:var(--navy-3);font-weight:600;">+' . (count($locs) - 3) . ' more</span>';
                            ?>
                        </td>
                        <?php foreach ($fleet_sizes as $size): ?>
                        <td style="padding:8px 5px;text-align:center;border-left:1px solid #f0f0f0;background:<?php echo $vendor['fleet_by_size'][$size]['online'] > 0 ? '#d1fae5' : '#fafafa'; ?>;color:<?php echo $vendor['fleet_by_size'][$size]['online'] > 0 ? '#065f46' : '#cbd5e1'; ?>;font-weight:700;">
                            <?php echo $vendor['fleet_by_size'][$size]['online'] ?: '-'; ?>
                        </td>
                        <td style="padding:8px 5px;text-align:center;background:<?php echo $vendor['fleet_by_size'][$size]['offline'] > 0 ? '#fee2e2' : '#fafafa'; ?>;color:<?php echo $vendor['fleet_by_size'][$size]['offline'] > 0 ? '#991b1b' : '#cbd5e1'; ?>;font-weight:700;">
                            <?php echo $vendor['fleet_by_size'][$size]['offline'] ?: '-'; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding:8px 10px;text-align:center;font-weight:800;color:var(--navy);font-size:14px;border-left:2px solid var(--border);background:#f8fafc;">
                            <?php echo $vendor['total']; ?>
                            <div style="font-size:9px;color:var(--muted);font-weight:600;margin-top:2px;">
                                <span style="color:#10b981;">🟢<?php echo $vendor['total_online']; ?></span>
                                <span style="color:#ef4444;margin-left:4px;">🔴<?php echo $vendor['total_offline']; ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top:20px;padding:15px;background:#f8fafc;border-radius:8px;border-left:4px solid var(--navy-3);">
            <div style="font-size:11px;color:var(--muted);line-height:1.8;">
                <strong style="color:var(--navy);">Legend:</strong> 
                <span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:4px;margin:0 5px;font-weight:700;">ON</span> = Online vehicles | 
                <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;margin:0 5px;font-weight:700;">OFF</span> = Offline vehicles
            </div>
        </div>
    </div>
    
    <!-- Fleet Summary by Tonnage -->
    <div style="background:white;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.06);overflow-x:auto;">
        <h2 style="color:var(--navy);font-size:22px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <span>⚖️</span> Fleet Summary by Vehicle Tonnage
        </h2>
        
        <table style="width:100%;border-collapse:collapse;min-width:1400px;font-size:12px;">
            <thead>
                <tr style="background:linear-gradient(135deg,var(--navy) 0%,var(--navy-2) 100%);color:white;">
                    <th style="padding:12px 10px;text-align:left;font-weight:700;position:sticky;left:0;background:var(--navy);z-index:2;">STATE</th>
                    <th style="padding:12px 10px;text-align:left;font-weight:700;min-width:180px;">VENDOR NAME</th>
                    <th style="padding:12px 10px;text-align:left;font-weight:700;">MOBILE</th>
                    <?php foreach ($fleet_tonnages as $tonnage): ?>
                    <th colspan="2" style="padding:12px 10px;text-align:center;font-weight:700;border-left:1px solid rgba(255,255,255,0.2);">
                        <?php echo htmlspecialchars($tonnage); ?>
                    </th>
                    <?php endforeach; ?>
                    <th style="padding:12px 10px;text-align:center;font-weight:700;border-left:2px solid rgba(255,255,255,0.4);">TOTAL</th>
                </tr>
                <tr style="background:var(--navy-2);color:white;font-size:10px;">
                    <th colspan="3"></th>
                    <?php foreach ($fleet_tonnages as $tonnage): ?>
                    <th style="padding:6px 5px;text-align:center;border-left:1px solid rgba(255,255,255,0.2);background:#d1fae5;color:#065f46;">ON</th>
                    <th style="padding:6px 5px;text-align:center;background:#fee2e2;color:#991b1b;">OFF</th>
                    <?php endforeach; ?>
                    <th style="padding:6px 5px;text-align:center;border-left:2px solid rgba(255,255,255,0.4);"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($by_state as $state => $vendors): ?>
                    <?php foreach ($vendors as $idx => $vendor): ?>
                    <tr style="border-bottom:1px solid #f0f0f0;<?php echo $idx === 0 ? 'border-top:3px solid var(--amber);' : ''; ?>">
                        <?php if ($idx === 0): ?>
                        <td rowspan="<?php echo count($vendors); ?>" 
                            style="padding:12px 10px;font-weight:800;color:var(--navy);background:#f8fafc;border-right:2px solid var(--border);position:sticky;left:0;z-index:1;vertical-align:top;">
                            <?php echo htmlspecialchars($state); ?>
                        </td>
                        <?php endif; ?>
                        <td style="padding:12px 10px;font-weight:600;color:var(--text);">
                            <?php echo htmlspecialchars($vendor['name']); ?>
                        </td>
                        <td style="padding:12px 10px;color:var(--muted);font-size:11px;">
                            <?php echo htmlspecialchars($vendor['phone']); ?>
                        </td>
                        <?php foreach ($fleet_tonnages as $tonnage): ?>
                        <td style="padding:8px 5px;text-align:center;border-left:1px solid #f0f0f0;background:<?php echo ($vendor['fleet_by_tonnage'][$tonnage]['online'] ?? 0) > 0 ? '#d1fae5' : '#fafafa'; ?>;color:<?php echo ($vendor['fleet_by_tonnage'][$tonnage]['online'] ?? 0) > 0 ? '#065f46' : '#cbd5e1'; ?>;font-weight:700;">
                            <?php echo ($vendor['fleet_by_tonnage'][$tonnage]['online'] ?? 0) ?: '-'; ?>
                        </td>
                        <td style="padding:8px 5px;text-align:center;background:<?php echo ($vendor['fleet_by_tonnage'][$tonnage]['offline'] ?? 0) > 0 ? '#fee2e2' : '#fafafa'; ?>;color:<?php echo ($vendor['fleet_by_tonnage'][$tonnage]['offline'] ?? 0) > 0 ? '#991b1b' : '#cbd5e1'; ?>;font-weight:700;">
                            <?php echo ($vendor['fleet_by_tonnage'][$tonnage]['offline'] ?? 0) ?: '-'; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding:8px 10px;text-align:center;font-weight:800;color:var(--navy);font-size:14px;border-left:2px solid var(--border);background:#f8fafc;">
                            <?php echo $vendor['total']; ?>
                            <div style="font-size:9px;color:var(--muted);font-weight:600;margin-top:2px;">
                                <span style="color:#10b981;">🟢<?php echo $vendor['total_online']; ?></span>
                                <span style="color:#ef4444;margin-left:4px;">🔴<?php echo $vendor['total_offline']; ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top:20px;padding:15px;background:#f8fafc;border-radius:8px;border-left:4px solid var(--amber);">
            <div style="font-size:11px;color:var(--muted);line-height:1.8;">
                <strong style="color:var(--navy);">Legend:</strong> 
                <span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:4px;margin:0 5px;font-weight:700;">ON</span> = Online vehicles | 
                <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;margin:0 5px;font-weight:700;">OFF</span> = Offline vehicles
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- DETAILED VIEW - Original grouped tables -->
    <!-- ══════════════════════════════════════════════════════════ -->
      <?php foreach ($grouped as $groupKey => $gVehicles):
        $g_online  = count(array_filter($gVehicles, fn($v) => $v['_online']));
        $g_offline = count($gVehicles) - $g_online;
      ?>
      <div class="group-section">
        <div class="group-header">
          <div class="gh-left">
            <span>
              <?php
              $icon = match($f_group) {
                'type'   => '🚛',
                'status' => ($groupKey === 'Online' ? '🟢' : '🔴'),
                'city','state' => '📍',
                default  => '📦'
              };
              echo $icon . ' ' . htmlspecialchars($groupKey);
              ?>
            </span>
            <span class="group-badge"><?php echo count($gVehicles); ?> vehicles</span>
          </div>
          <div class="group-pills">
            <?php if ($g_online > 0): ?><span class="gpill gpill-green">🟢 <?php echo $g_online; ?> Online</span><?php endif; ?>
            <?php if ($g_offline > 0): ?><span class="gpill gpill-red">🔴 <?php echo $g_offline; ?> Offline</span><?php endif; ?>
          </div>
        </div>

        <?php if (empty($gVehicles)): ?>
        <div class="empty-state"><div class="es-icon">📭</div><p>No vehicles in this group</p></div>
        <?php else: ?>
        <div class="vehicle-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Status</th>
                <th>Vehicle No.</th>
                <th>Size / Type</th>
                <th>Driver</th>
                <th>Vendor</th>
                <th>📍 Location</th>
                <th>Last Seen</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gVehicles as $v):
                $isOnline = $v['_online'];
                $hasLoc   = $has_location_columns && !empty($v['last_latitude']) && !empty($v['last_longitude']);
                $mapLink  = $hasLoc ? 'https://www.google.com/maps?q='.$v['last_latitude'].','.$v['last_longitude'] : '';
              ?>
              <tr class="<?php echo $isOnline ? 'is-online' : 'is-offline'; ?>">
                <td>
                  <span class="badge <?php echo $isOnline ? 'badge-online' : 'badge-offline'; ?>">
                    <?php echo $isOnline ? '🟢 Online' : '🔴 Offline'; ?>
                  </span>
                </td>
                <td>
                  <div class="vehicle-num"><?php echo htmlspecialchars($v['vehicle_number']); ?></div>
                  <div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($v['vehicle_name']); ?> <?php echo htmlspecialchars($v['vehicle_year']); ?></div>
                </td>
                <td>
                  <span class="badge badge-size"><?php echo htmlspecialchars($v['vehicle_size_feet']); ?></span><br>
                  <span class="badge badge-type" style="margin-top:4px;"><?php echo htmlspecialchars($v['vehicle_type']); ?></span>
                </td>
                <td>
                  <div class="driver-name"><?php echo htmlspecialchars($v['driver_name']); ?></div>
                  <div style="font-size:11px;color:var(--muted);">@<?php echo htmlspecialchars($v['driver_username']); ?></div>
                </td>
                <td>
                  <div class="vendor-name"><?php echo htmlspecialchars($v['vendor_name']); ?></div>
                  <div class="vendor-phone"><?php echo htmlspecialchars($v['vendor_phone']); ?></div>
                </td>
                <td>
                  <?php if ($hasLoc): ?>
                    <a href="<?php echo $mapLink; ?>" target="_blank" class="loc-link">📍 View Map</a>
                    <?php
                    $displayAddr = !empty($v['_address']) ? $v['_address'] : '';
                    $isCoords    = preg_match('/^GPS:/', trim($displayAddr));
                    if (!empty($displayAddr) && !$isCoords):
                        $uid = $v['id'];
                    ?>
                    <div class="loc-addr-wrap">
                      <span class="loc-addr-short"><?php echo htmlspecialchars(substr($displayAddr, 0, 40)); ?><?php if(strlen($displayAddr) > 40) echo '...'; ?></span>
                      <br><button class="read-more-btn" onclick="showFullAddress(<?php echo $uid; ?>, '<?php echo htmlspecialchars(addslashes($displayAddr)); ?>')">📍 Full Address</button>
                    </div>
                    <?php else: ?>
                    <div class="loc-addr-wrap">
                      <span style="font-size:11px;color:#f59e0b;">⏳ Resolving...</span>
                    </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="no-loc">No location</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                  <?php echo $has_location_columns ? timeAgo($v['last_location_update']) : '<span style="color:#cbd5e1">N/A</span>'; ?>
                </td>
                <td>
                  <button class="btn-assign" onclick="openAssignModal(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars(addslashes($v['vehicle_number'])); ?>', '<?php echo htmlspecialchars(addslashes($v['vehicle_name'])); ?>', '<?php echo htmlspecialchars(addslashes($v['driver_name'])); ?>', '<?php echo htmlspecialchars(addslashes($v['vendor_name'])); ?>')">
                    📋 Assign
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<!-- Assignment Modal -->
<div id="assignModal" class="assign-modal">
  <div class="assign-modal-content">
    <div class="assign-modal-header">
      <h3>📋 Assign Vehicle to Shipment</h3>
      <button class="assign-modal-close" onclick="closeAssignModal()">×</button>
    </div>
    <div class="assign-modal-body">
      <?php if (isset($success_message)): ?>
        <div class="alert-box alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="alert-box alert-error"><?php echo $error_message; ?></div>
      <?php endif; ?>
      
      <div class="vehicle-info-box" id="vehicleInfoBox">
        <h4>🚛 Selected Vehicle</h4>
        <p id="vehicleInfoText">No vehicle selected</p>
      </div>
      
      <form method="POST" id="assignForm">
        <input type="hidden" name="action" value="assign_vehicle">
        <input type="hidden" name="vehicle_id" id="modal_vehicle_id">
        
        <div class="form-group-modal">
          <label>Vehicle Selection</label>
          <input type="text" id="selected_vehicle_display" readonly style="background:#f8fafc;cursor:not-allowed;" placeholder="Select vehicle from table">
        </div>
        
        <div class="payment-section">
          <h4>💰 Payment Details</h4>
          <div style="background:#fff;border:1px solid #f59e0b;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#92400e;">
            <strong>ℹ️ Note:</strong> The advance amount and transaction ID you enter here will automatically appear as the <strong>first payment entry</strong> in <a href="../manage_vendor_assignments.php" target="_blank" style="color:#2563c4;font-weight:700;">Vendor Assignments & Payments</a>.
          </div>

          <div class="form-group-modal">
            <label>Payment Status *</label>
            <select name="payment_status" id="payment_status" required onchange="updatePaymentFields()">
              <option value="unpaid">Unpaid</option>
              <option value="advance_paid">Advance Paid</option>
              <option value="partially_paid">Partially Paid</option>
              <option value="fully_paid">Fully Paid</option>
            </select>
          </div>
          
          <div class="form-row">
            <div class="form-group-modal">
              <label>Total Payment Amount (₹) *</label>
              <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0" required placeholder="0.00" onchange="calculateRemaining()">
            </div>
            
            <div class="form-group-modal" id="advance_group" style="display:none;">
              <label>Advance Amount (₹)</label>
              <input type="number" name="advance_amount" id="advance_amount" step="0.01" min="0" placeholder="0.00" onchange="calculateRemaining()">
            </div>
            
            <div class="form-group-modal" id="transaction_group">
              <label>Transaction ID / Reference</label>
              <input type="text" name="vendor_transaction_id" id="vendor_transaction_id" placeholder="e.g. UTR123456789">
            </div>
          </div>
          
          <div id="remaining_display" style="display:none; background:white; padding:12px; border-radius:6px; margin-top:10px;">
            <strong style="color:var(--navy);">Remaining Amount:</strong> 
            <span id="remaining_text" style="color:var(--red); font-size:18px; font-weight:800;">₹0.00</span>
          </div>
        </div>
        
        <div class="form-group-modal">
          <label>Notes</label>
          <textarea name="notes" placeholder="Additional instructions or notes (optional)"></textarea>
        </div>
        
        <button type="submit" class="btn-submit-assign">Proceed to Select Shipments ➔</button>
      </form>
    </div>
  </div>
</div>

<!-- Address Modal -->
<div id="addressModal" class="address-modal">
  <div class="address-modal-content">
    <div class="address-modal-header">
      <h3>📍 Complete Address</h3>
      <button class="address-modal-close" onclick="closeAddressModal()">×</button>
    </div>
    <div class="address-modal-body" id="modalAddressText"></div>
  </div>
</div>

<script>
// Show full address in modal
function showFullAddress(id, address) {
  document.getElementById('modalAddressText').textContent = address;
  document.getElementById('addressModal').style.display = 'block';
}

// Close modal
function closeAddressModal() {
  document.getElementById('addressModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  var modal = document.getElementById('addressModal');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
}

// ═══════════════════════════════════════════════════════════
// AUTO-REFRESH — Backend redirect after 30 seconds
// ═══════════════════════════════════════════════════════════
var secs = 30;
var cd = document.getElementById('countdown');
var isModalOpen = false;

setInterval(function() {
  if (isModalOpen) return;
  secs--;
  if (cd) cd.textContent = secs;
  if (secs <= 0) {
    window.location.href = window.location.href;
  }
}, 1000);

// ═══════════════════════════════════════════════════════════
// SEARCH — Keep icon visible, debounced submit
// ═══════════════════════════════════════════════════════════
var searchInput = document.getElementById('searchInput');
var searchTimer = null;

if (searchInput) {
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
      document.getElementById('filterForm').submit();
    }, 800);
  });
  
  searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(searchTimer);
      document.getElementById('filterForm').submit();
    }
  });
}

// ═══════════════════════════════════════════════════════════
// ASSIGNMENT MODAL FUNCTIONS
// ═══════════════════════════════════════════════════════════
function openAssignModal(vehicleId, vehicleNumber, vehicleName, driverName, vendorName) {
  isModalOpen = true;
  if (cd) cd.textContent = "⏸️";
  document.getElementById('modal_vehicle_id').value = vehicleId;
  document.getElementById('vehicleInfoText').innerHTML = 
    '<strong>' + vehicleNumber + '</strong> - ' + vehicleName + '<br>' +
    '<strong>Driver:</strong> ' + driverName + ' | <strong>Vendor:</strong> ' + vendorName;
  document.getElementById('selected_vehicle_display').value = vehicleNumber + ' - ' + vehicleName;
  document.getElementById('assignModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeAssignModal() {
  isModalOpen = false;
  secs = 30; // Reset timer when modal is closed
  document.getElementById('assignModal').style.display = 'none';
  document.body.style.overflow = 'auto';
  // Reset form
  document.getElementById('assignForm').reset();
}

function updatePaymentFields() {
  var status = document.getElementById('payment_status').value;
  var advanceGroup = document.getElementById('advance_group');
  var remainingDisplay = document.getElementById('remaining_display');
  
  if (status === 'advance_paid' || status === 'partially_paid') {
    advanceGroup.style.display = 'block';
    remainingDisplay.style.display = 'block';
  } else if (status === 'fully_paid') {
    advanceGroup.style.display = 'none';
    remainingDisplay.style.display = 'none';
    document.getElementById('advance_amount').value = '';
  } else {
    advanceGroup.style.display = 'none';
    remainingDisplay.style.display = 'none';
    document.getElementById('advance_amount').value = '';
  }
  
  calculateRemaining();
}

function calculateRemaining() {
  var total = parseFloat(document.getElementById('payment_amount').value) || 0;
  var advance = parseFloat(document.getElementById('advance_amount').value) || 0;
  var remaining = total - advance;
  
  if (remaining < 0) remaining = 0;
  
  document.getElementById('remaining_text').textContent = '₹' + remaining.toFixed(2);
}

// Close modals when clicking outside
window.onclick = function(event) {
  var addressModal = document.getElementById('addressModal');
  var assignModal = document.getElementById('assignModal');
  
  if (event.target == addressModal) {
    addressModal.style.display = 'none';
  }
  if (event.target == assignModal) {
    closeAssignModal();
  }
}

</script>
</body>
</html>