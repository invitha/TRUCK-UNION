-- Add location tracking columns to vehicles table
ALTER TABLE `vehicles` 
ADD COLUMN `is_online` TINYINT(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `last_latitude` DECIMAL(10, 8) NULL AFTER `is_online`,
ADD COLUMN `last_longitude` DECIMAL(11, 8) NULL AFTER `last_latitude`,
ADD COLUMN `last_location_update` TIMESTAMP NULL AFTER `last_longitude`,
ADD COLUMN `location_address` VARCHAR(500) NULL AFTER `last_location_update`,
ADD INDEX `idx_is_online` (`is_online`),
ADD INDEX `idx_last_location_update` (`last_location_update`);
