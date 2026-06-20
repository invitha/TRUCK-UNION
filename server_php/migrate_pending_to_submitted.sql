-- ============================================================================
-- Migration Script: Convert 'pending' status to 'submitted'
-- Run this ONLY if you already have data in vendor_kyc table
-- ============================================================================

-- Step 1: Update any existing 'pending' records to 'submitted'
UPDATE vendor_kyc 
SET kyc_status = 'submitted' 
WHERE kyc_status = 'pending';

-- Step 2: Alter the table to remove 'pending' from ENUM
ALTER TABLE vendor_kyc 
MODIFY COLUMN kyc_status ENUM('submitted', 'verified', 'rejected') DEFAULT 'submitted';

-- ============================================================================
-- Verification Query - Run this to check the changes
-- ============================================================================

-- Check if any pending records remain (should return 0)
SELECT COUNT(*) as pending_count FROM vendor_kyc WHERE kyc_status = 'pending';

-- Check the new column definition
SHOW COLUMNS FROM vendor_kyc LIKE 'kyc_status';

-- ============================================================================
-- NOTES:
-- - This script is safe to run multiple times
-- - If you get an error about 'pending' not existing, it means the migration
--   already completed successfully
-- ============================================================================
