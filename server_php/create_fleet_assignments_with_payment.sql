-- Complete fleet_assignments table with payment tracking
-- Run this script to create the table with all payment columns included

CREATE TABLE IF NOT EXISTS `fleet_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `al_number` VARCHAR(100) NOT NULL,
  `vehicle_id` INT NOT NULL,
  `vendor_firebase_uid` VARCHAR(255) NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `vehicle_name` VARCHAR(255) NOT NULL,
  `driver_name` VARCHAR(255) NOT NULL,
  `assigned_by` VARCHAR(255) DEFAULT 'Internal Team',
  `pickup_location` VARCHAR(500) NOT NULL,
  `delivery_location` VARCHAR(500) NOT NULL,
  `assignment_date` DATETIME NOT NULL,
  `expected_completion_date` DATETIME NULL,
  `actual_completion_date` DATETIME NULL,
  `status` ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'active',
  `notes` TEXT NULL,
  
  -- Payment tracking columns
  `payment_status` ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid',
  `payment_amount` DECIMAL(10,2) DEFAULT 0.00,
  `advance_amount` DECIMAL(10,2) DEFAULT 0.00,
  `remaining_amount` DECIMAL(10,2) DEFAULT 0.00,
  `payment_date` DATETIME NULL,
  `payment_notes` TEXT NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX `idx_al_number` (`al_number`),
  INDEX `idx_vehicle_id` (`vehicle_id`),
  INDEX `idx_vendor_uid` (`vendor_firebase_uid`),
  INDEX `idx_status` (`status`),
  INDEX `idx_payment_status` (`payment_status`),
  
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
