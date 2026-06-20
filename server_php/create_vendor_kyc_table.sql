-- ============================================================================
-- TRUCK UNION - Vendor KYC Table Creation Script
-- Run this SQL in phpMyAdmin to create the vendor_kyc table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vendor_kyc` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` VARCHAR(255) NOT NULL,
  `account_type` ENUM('individual', 'business') DEFAULT 'individual',
  
  -- Personal Details
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `aadhaar_number` VARCHAR(12) NOT NULL,
  `pan_number` VARCHAR(10) NOT NULL,
  
  -- Business Details (Optional for individual accounts)
  `company_name` VARCHAR(255) DEFAULT NULL,
  `gst_number` VARCHAR(15) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  
  -- Bank Account Details (Mandatory for all)
  `bank_account_name` VARCHAR(255) NOT NULL,
  `bank_account_number` VARCHAR(20) NOT NULL,
  `ifsc_code` VARCHAR(11) NOT NULL,
  
  -- Document Paths
  `aadhaar_doc` VARCHAR(500) DEFAULT NULL,
  `pan_doc` VARCHAR(500) DEFAULT NULL,
  `photo_doc` VARCHAR(500) DEFAULT NULL,
  `gst_doc` VARCHAR(500) DEFAULT NULL,
  `address_doc` VARCHAR(500) DEFAULT NULL,
  `bank_account_photo` VARCHAR(500) DEFAULT NULL,
  
  -- KYC Status
  `kyc_status` ENUM('submitted', 'verified', 'rejected') DEFAULT 'submitted',
  `rejection_reason` TEXT DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `firebase_uid` (`firebase_uid`),
  UNIQUE KEY `aadhaar_number` (`aadhaar_number`),
  UNIQUE KEY `pan_number` (`pan_number`),
  UNIQUE KEY `bank_account_number` (`bank_account_number`),
  KEY `kyc_status` (`kyc_status`),
  KEY `account_type` (`account_type`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Indexes are already created in the table definition above with KEY statements
-- No need to create them separately
-- ============================================================================

-- ============================================================================
-- Optional: Add sample data for testing
-- ============================================================================

-- INSERT INTO `vendor_kyc` (
--   `firebase_uid`, `account_type`, `name`, `email`, `phone`,
--   `aadhaar_number`, `pan_number`, 
--   `bank_account_name`, `bank_account_number`, `ifsc_code`,
--   `kyc_status`
-- ) VALUES (
--   'test_firebase_uid_123',
--   'individual',
--   'Test Vendor',
--   'test@example.com',
--   '9876543210',
--   '123456789012',
--   'ABCDE1234F',
--   'Test Vendor',
--   '1234567890123456',
--   'ABCD0123456',
--   'submitted'
-- );

-- ============================================================================
-- Verify table creation
-- ============================================================================

-- Run this to check if table was created successfully:
-- DESCRIBE vendor_kyc;

-- Run this to see all records:
-- SELECT * FROM vendor_kyc;

-- ============================================================================
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin
-- 2. Select your database (the one used in db_config.php)
-- 3. Click on "SQL" tab
-- 4. Copy and paste this entire SQL script
-- 5. Click "Go" to execute
-- 6. Verify the table was created by checking the "Structure" tab
-- ============================================================================
