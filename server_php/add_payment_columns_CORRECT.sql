-- Add payment tracking columns to fleet_assignments table
-- Database: royaldxd_abra_crm (as used in dashboard.php)
-- 
-- IMPORTANT: Run this ONLY if the fleet_assignments table already exists
-- If table doesn't exist, use create_fleet_assignments_with_payment_CORRECT.sql instead

USE royaldxd_abra_crm;

-- Check current table structure first
SELECT 'Current fleet_assignments columns:' AS info;
DESCRIBE fleet_assignments;

-- Add payment columns
ALTER TABLE fleet_assignments
ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' AFTER notes,
ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status,
ADD COLUMN IF NOT EXISTS advance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount,
ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(10,2) DEFAULT 0.00 AFTER advance_amount,
ADD COLUMN IF NOT EXISTS payment_date DATETIME NULL AFTER remaining_amount,
ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER payment_date;

-- Add index for payment_status for faster queries
ALTER TABLE fleet_assignments
ADD INDEX IF NOT EXISTS idx_payment_status (payment_status);

-- Verify the changes
SELECT 'Updated fleet_assignments columns:' AS info;
DESCRIBE fleet_assignments;

SELECT '✅ Payment columns added successfully!' AS status;
