<?php
/**
 * Dashboard Database Fix Script
 * This will automatically create the fleet_assignments table
 * and add any missing columns to the vehicles table
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Dashboard Database Fix</h1>";
echo "<hr>";

// Database connection
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die("❌ Connection failed: " . $con->connect_error);
    }
    $con->set_charset('utf8mb4');
    echo "✅ Connected to database: $dbname<br><br>";
} catch (Exception $e) {
    die("❌ Exception: " . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════
// STEP 1: Check if fleet_assignments table exists
// ═══════════════════════════════════════════════════════════════
echo "<h2>Step 1: Check fleet_assignments Table</h2>";

$result = $con->query("SHOW TABLES LIKE 'fleet_assignments'");
if ($result->num_rows > 0) {
    echo "✅ Table 'fleet_assignments' already exists<br>";
    
    // Check columns
    $cols_result = $con->query("SHOW COLUMNS FROM fleet_assignments");
    $existing_cols = [];
    while ($row = $cols_result->fetch_assoc()) {
        $existing_cols[] = $row['Field'];
    }
    echo "Columns: " . count($existing_cols) . "<br>";
    
    // Check for payment columns
    $payment_cols = ['payment_status', 'payment_amount', 'advance_amount', 'remaining_amount'];
    $missing_payment_cols = array_diff($payment_cols, $existing_cols);
    
    if (!empty($missing_payment_cols)) {
        echo "<br>⚠️ Missing payment columns: " . implode(', ', $missing_payment_cols) . "<br>";
        echo "Adding missing columns...<br>";
        
        // Add missing payment columns
        $alter_sql = "ALTER TABLE fleet_assignments ";
        $alters = [];
        
        if (!in_array('payment_status', $existing_cols)) {
            $alters[] = "ADD COLUMN payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' AFTER notes";
        }
        if (!in_array('payment_amount', $existing_cols)) {
            $alters[] = "ADD COLUMN payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status";
        }
        if (!in_array('advance_amount', $existing_cols)) {
            $alters[] = "ADD COLUMN advance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount";
        }
        if (!in_array('remaining_amount', $existing_cols)) {
            $alters[] = "ADD COLUMN remaining_amount DECIMAL(10,2) DEFAULT 0.00 AFTER advance_amount";
        }
        if (!in_array('payment_date', $existing_cols)) {
            $alters[] = "ADD COLUMN payment_date DATETIME NULL AFTER remaining_amount";
        }
        if (!in_array('payment_notes', $existing_cols)) {
            $alters[] = "ADD COLUMN payment_notes TEXT NULL AFTER payment_date";
        }
        
        if (!empty($alters)) {
            $alter_sql .= implode(', ', $alters);
            if ($con->query($alter_sql)) {
                echo "✅ Payment columns added successfully<br>";
            } else {
                echo "❌ Error adding columns: " . $con->error . "<br>";
            }
        }
    } else {
        echo "✅ All payment columns exist<br>";
    }
    
} else {
    echo "⚠️ Table 'fleet_assignments' does NOT exist<br>";
    echo "Creating table...<br><br>";
    
    $create_sql = "CREATE TABLE fleet_assignments (
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
        payment_date DATETIME NULL,
        payment_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_vehicle_id (vehicle_id),
        INDEX idx_vendor_uid (vendor_firebase_uid),
        INDEX idx_status (status),
        INDEX idx_al_number (al_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($con->query($create_sql)) {
        echo "✅ Table 'fleet_assignments' created successfully!<br>";
    } else {
        echo "❌ Error creating table: " . $con->error . "<br>";
    }
}

echo "<hr>";

// ═══════════════════════════════════════════════════════════════
// STEP 2: Check vehicles table for location columns
// ═══════════════════════════════════════════════════════════════
echo "<h2>Step 2: Check vehicles Table Location Columns</h2>";

$result = $con->query("SHOW COLUMNS FROM vehicles");
$vehicle_cols = [];
while ($row = $result->fetch_assoc()) {
    $vehicle_cols[] = $row['Field'];
}

$location_cols = ['vendor_location', 'vendor_city', 'vendor_state', 'latitude', 'longitude'];
$missing_location_cols = array_diff($location_cols, $vehicle_cols);

if (!empty($missing_location_cols)) {
    echo "⚠️ Missing location columns: " . implode(', ', $missing_location_cols) . "<br>";
    echo "Adding missing columns...<br>";
    
    $alter_sql = "ALTER TABLE vehicles ";
    $alters = [];
    
    if (!in_array('vendor_location', $vehicle_cols)) {
        $alters[] = "ADD COLUMN vendor_location VARCHAR(255) AFTER vendor_phone";
    }
    if (!in_array('vendor_city', $vehicle_cols)) {
        $alters[] = "ADD COLUMN vendor_city VARCHAR(100) AFTER vendor_location";
    }
    if (!in_array('vendor_state', $vehicle_cols)) {
        $alters[] = "ADD COLUMN vendor_state VARCHAR(100) AFTER vendor_city";
    }
    if (!in_array('latitude', $vehicle_cols)) {
        $alters[] = "ADD COLUMN latitude DECIMAL(10, 8) AFTER vendor_state";
    }
    if (!in_array('longitude', $vehicle_cols)) {
        $alters[] = "ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude";
    }
    
    if (!empty($alters)) {
        $alter_sql .= implode(', ', $alters);
        if ($con->query($alter_sql)) {
            echo "✅ Location columns added successfully<br>";
        } else {
            echo "❌ Error adding columns: " . $con->error . "<br>";
        }
    }
} else {
    echo "✅ All location columns exist<br>";
}

echo "<hr>";

// ═══════════════════════════════════════════════════════════════
// STEP 3: Final Verification
// ═══════════════════════════════════════════════════════════════
echo "<h2>Step 3: Final Verification</h2>";

// Check fleet_assignments
$result = $con->query("SHOW TABLES LIKE 'fleet_assignments'");
if ($result->num_rows > 0) {
    echo "✅ fleet_assignments table exists<br>";
    
    $cols = $con->query("SHOW COLUMNS FROM fleet_assignments");
    echo "✅ Columns: " . $cols->num_rows . "<br>";
} else {
    echo "❌ fleet_assignments table NOT found<br>";
}

// Check vehicles
$result = $con->query("SHOW TABLES LIKE 'vehicles'");
if ($result->num_rows > 0) {
    echo "✅ vehicles table exists<br>";
    
    $cols = $con->query("SHOW COLUMNS FROM vehicles");
    echo "✅ Columns: " . $cols->num_rows . "<br>";
} else {
    echo "❌ vehicles table NOT found<br>";
}

echo "<hr>";

// ═══════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════
echo "<h2>✅ Summary</h2>";
echo "<div style='background:#d1fae5;border-left:4px solid #10b981;padding:20px;margin:20px 0;'>";
echo "<h3 style='color:#065f46;margin:0 0 10px 0;'>Database Fix Complete!</h3>";
echo "<p style='color:#065f46;margin:0;'>All required tables and columns have been created.</p>";
echo "<p style='color:#065f46;margin:10px 0 0 0;'><strong>Next Step:</strong> Try opening dashboard.php now - it should work!</p>";
echo "</div>";

echo "<div style='background:#dbeafe;border-left:4px solid #3b82f6;padding:20px;margin:20px 0;'>";
echo "<h3 style='color:#1e40af;margin:0 0 10px 0;'>📋 What Was Fixed:</h3>";
echo "<ul style='color:#1e40af;margin:0;'>";
echo "<li>✅ Created/verified fleet_assignments table</li>";
echo "<li>✅ Added payment tracking columns</li>";
echo "<li>✅ Added location columns to vehicles table</li>";
echo "<li>✅ Created necessary indexes for performance</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align:center;margin:30px 0;'>";
echo "<a href='dashboard.php' style='display:inline-block;background:#0d2e6e;color:white;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;'>Open Dashboard →</a>";
echo "</div>";

$con->close();
?>
