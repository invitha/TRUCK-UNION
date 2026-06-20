-- Add verified_at column to vendor_kyc table (safe migration)
-- This checks if the column exists before adding it

SET @dbname = DATABASE();
SET @tablename = 'vendor_kyc';
SET @columnname = 'verified_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1', -- Column exists, do nothing
  'ALTER TABLE `vendor_kyc` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `rejection_reason`' -- Column doesn't exist, add it
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
