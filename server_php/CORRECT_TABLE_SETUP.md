# Correct Fleet Assignments Table Setup

## Database Information
- **Database Name:** `royaldxd_abra_crm` (from dashboard.php)
- **Table Name:** `fleet_assignments`

## Current Situation
The dashboard code is already checking for payment columns:
```php
$check_cols = $con->query("SHOW COLUMNS FROM fleet_assignments LIKE 'payment_status'");
$has_payment_cols = $check_cols->num_rows > 0;
```

## Step 1: Check if Table Exists

Run this in phpMyAdmin:
```sql
SHOW TABLES LIKE 'fleet_assignments';
```

## Step 2A: If Table DOES NOT Exist - Create It

Run this complete SQL:
```sql
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
  `assignment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
```

## Step 2B: If Table EXISTS but Missing Payment Columns - Add Them

First check what columns exist:
```sql
DESCRIBE fleet_assignments;
```

Then add missing payment columns:
```sql
ALTER TABLE fleet_assignments
ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' AFTER notes,
ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status,
ADD COLUMN IF NOT EXISTS advance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount,
ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(10,2) DEFAULT 0.00 AFTER advance_amount,
ADD COLUMN IF NOT EXISTS payment_date DATETIME NULL AFTER remaining_amount,
ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER payment_date,
ADD INDEX IF NOT EXISTS idx_payment_status (payment_status);
```

## Step 3: Verify Setup

Run this to confirm all columns exist:
```sql
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'royaldxd_abra_crm' 
  AND TABLE_NAME = 'fleet_assignments'
ORDER BY ORDINAL_POSITION;
```

## Expected Columns

You should see these columns:
1. id
2. al_number
3. vehicle_id
4. vendor_firebase_uid
5. vehicle_number
6. vehicle_name
7. driver_name
8. assigned_by
9. pickup_location
10. delivery_location
11. assignment_date
12. expected_completion_date
13. actual_completion_date
14. status
15. notes
16. **payment_status** ← NEW
17. **payment_amount** ← NEW
18. **advance_amount** ← NEW
19. **remaining_amount** ← NEW
20. **payment_date** ← NEW
21. **payment_notes** ← NEW
22. created_at
23. updated_at

## Dashboard Code Compatibility

The dashboard.php already handles both scenarios:
- ✅ **With payment columns:** Uses full INSERT with payment fields
- ✅ **Without payment columns:** Uses fallback INSERT without payment fields

Once you add the payment columns, the dashboard will automatically start using them!
