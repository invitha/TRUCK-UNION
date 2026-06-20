-- ═══════════════════════════════════════════════════════════════
-- CREATE FLEET ASSIGNMENTS TABLE - FINAL VERSION
-- ═══════════════════════════════════════════════════════════════
-- This creates the fleet_assignments table with all required columns
-- including payment tracking features
-- ═══════════════════════════════════════════════════════════════

-- Drop table if exists (CAUTION: This will delete all data!)
-- Uncomment the line below ONLY if you want to recreate the table from scratch
-- DROP TABLE IF EXISTS fleet_assignments;

-- Create fleet_assignments table
CREATE TABLE IF NOT EXISTS fleet_assignments (
  -- Primary Key
  id INT AUTO_INCREMENT PRIMARY KEY,
  
  -- Assignment Details
  al_number VARCHAR(50) NOT NULL COMMENT 'Airway Bill / Assignment Number',
  vehicle_id INT NOT NULL COMMENT 'Foreign key to vehicles table',
  
  -- Vendor Information (denormalized for quick access)
  vendor_firebase_uid VARCHAR(255) COMMENT 'Vendor Firebase UID',
  vehicle_number VARCHAR(50) COMMENT 'Vehicle registration number',
  vehicle_name VARCHAR(100) COMMENT 'Vehicle name/model',
  driver_name VARCHAR(100) COMMENT 'Driver name',
  
  -- Assignment Information
  assigned_by VARCHAR(100) COMMENT 'Who assigned this (admin name)',
  pickup_location TEXT COMMENT 'Pickup address',
  delivery_location TEXT COMMENT 'Delivery address',
  expected_completion_date DATE COMMENT 'Expected delivery date',
  
  -- Status
  status ENUM('active', 'completed', 'cancelled') DEFAULT 'active' COMMENT 'Assignment status',
  notes TEXT COMMENT 'Additional notes/instructions',
  
  -- Payment Tracking
  payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' COMMENT 'Payment status',
  payment_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total payment amount',
  advance_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Advance payment received',
  remaining_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Remaining balance',
  payment_date DATETIME NULL COMMENT 'Payment completion date',
  payment_notes TEXT NULL COMMENT 'Payment related notes',
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update time',
  
  -- Indexes for performance
  INDEX idx_vehicle_id (vehicle_id),
  INDEX idx_vendor_uid (vendor_firebase_uid),
  INDEX idx_status (status),
  INDEX idx_al_number (al_number),
  INDEX idx_created_at (created_at)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fleet assignment tracking with payment management';

-- ═══════════════════════════════════════════════════════════════
-- VERIFICATION QUERY
-- ═══════════════════════════════════════════════════════════════
-- Run this to verify the table was created successfully:

SELECT 
    'Table created successfully!' as status,
    COUNT(*) as column_count 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'royaldxd_abra_crm' 
  AND TABLE_NAME = 'fleet_assignments';

-- ═══════════════════════════════════════════════════════════════
-- SAMPLE DATA (OPTIONAL)
-- ═══════════════════════════════════════════════════════════════
-- Uncomment to insert sample data for testing:

/*
INSERT INTO fleet_assignments (
    al_number, vehicle_id, vendor_firebase_uid, vehicle_number, 
    vehicle_name, driver_name, assigned_by, pickup_location, 
    delivery_location, expected_completion_date, status, notes,
    payment_status, payment_amount, advance_amount, remaining_amount
) VALUES (
    'AL123456', 1, 'sample_firebase_uid', 'KA01AB1234',
    'Tata Ace', 'Rajesh Kumar', 'Admin', 'Bangalore, Karnataka',
    'Chennai, Tamil Nadu', '2026-05-30', 'active', 'Handle with care',
    'advance_paid', 15000.00, 5000.00, 10000.00
);
*/

-- ═══════════════════════════════════════════════════════════════
-- SUCCESS MESSAGE
-- ═══════════════════════════════════════════════════════════════

SELECT '✅ Fleet Assignments Table Created Successfully!' as message;
SELECT 'You can now use dashboard.php without errors' as next_step;
