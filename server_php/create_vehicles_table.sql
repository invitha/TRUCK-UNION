-- Create vehicles table
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firebase_uid` VARCHAR(255) NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `vendor_email` VARCHAR(255) NOT NULL,
  `vendor_phone` VARCHAR(20) NOT NULL,
  `vendor_location` VARCHAR(500) NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `vehicle_name` VARCHAR(255) NOT NULL,
  `vehicle_year` VARCHAR(10) NOT NULL,
  `vehicle_type` VARCHAR(100) NOT NULL,
  `vehicle_size_feet` VARCHAR(50) NOT NULL,
  `driver_name` VARCHAR(255) NOT NULL,
  `driver_username` VARCHAR(255) NOT NULL,
  `driver_password` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_vehicle_number` (`vehicle_number`),
  INDEX `idx_firebase_uid` (`firebase_uid`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
