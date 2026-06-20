# Fleet Assignments Table Fix Guide

## Problem
Error: `#1146 - Table 'royaldxd_abra_crm.fleet_assignments' doesn't exist`

This means the `fleet_assignments` table hasn't been created in your database yet.

## Solution Steps

### Step 1: Check Database Name
Your error shows database name as `royaldxd_abra_crm` but your config shows `royaldxd_abra`.

**Action:** Verify which database you're actually using:
1. Open `server_php/db_config.php`
2. Check the `$db_name` variable
3. Make sure it matches your actual database name

### Step 2: Run Diagnostic Script
Upload and run this file to check your table status:
```
server_php/check_and_fix_fleet_assignments.php
```

Access it via: `https://yourdomain.com/server_php/check_and_fix_fleet_assignments.php`

This will tell you:
- ✅ If the table exists
- ✅ Which columns are present
- ✅ Which columns are missing
- ✅ If the vehicles table exists (required dependency)

### Step 3: Create the Table

#### Option A: Create Table with Payment Columns (Recommended)
Run this SQL file in phpMyAdmin:
```sql
server_php/create_fleet_assignments_with_payment.sql
```

This creates the complete table with all payment tracking columns included.

#### Option B: Create Table First, Then Add Payment Columns
1. First run: `server_php/create_fleet_assignments_table.sql`
2. Then run: `server_php/add_payment_columns.sql`

### Step 4: Verify Table Creation

Run this SQL query in phpMyAdmin:
```sql
DESCRIBE fleet_assignments;
```

You should see these columns:
- ✅ id
- ✅ al_number
- ✅ vehicle_id
- ✅ vendor_firebase_uid
- ✅ vehicle_number
- ✅ vehicle_name
- ✅ driver_name
- ✅ assigned_by
- ✅ pickup_location
- ✅ delivery_location
- ✅ assignment_date
- ✅ expected_completion_date
- ✅ actual_completion_date
- ✅ status
- ✅ notes
- ✅ **payment_status** (NEW)
- ✅ **payment_amount** (NEW)
- ✅ **advance_amount** (NEW)
- ✅ **remaining_amount** (NEW)
- ✅ **payment_date** (NEW)
- ✅ **payment_notes** (NEW)
- ✅ created_at
- ✅ updated_at

## Payment Status Values

The `payment_status` column can have these values:
- **unpaid**: No payment received yet
- **advance_paid**: Advance payment received
- **partially_paid**: Partial payment received
- **fully_paid**: Full payment completed

## Common Issues

### Issue 1: Vehicles table doesn't exist
**Error:** Cannot add foreign key constraint

**Fix:** Create the vehicles table first:
```sql
-- Run this file first
server_php/create_vehicles_table.sql
```

### Issue 2: Wrong database name
**Error:** Table doesn't exist in database X

**Fix:** 
1. Check your phpMyAdmin to see actual database name
2. Update `server_php/db_config.php` with correct database name
3. Restart your PHP server if needed

### Issue 3: Columns already exist
**Error:** Duplicate column name

**Fix:** This is actually fine! The `IF NOT EXISTS` clause prevents errors. The columns are already there.

## Files Created

1. ✅ `create_fleet_assignments_with_payment.sql` - Complete table with payment columns
2. ✅ `add_payment_columns.sql` - Updated with better error handling
3. ✅ `check_and_fix_fleet_assignments.php` - Diagnostic tool

## Quick Test

After creating the table, test it with this SQL:
```sql
-- Insert a test record
INSERT INTO fleet_assignments (
    al_number, vehicle_id, vendor_firebase_uid, 
    vehicle_number, vehicle_name, driver_name,
    pickup_location, delivery_location, assignment_date,
    payment_status, payment_amount
) VALUES (
    'TEST001', 1, 'test_firebase_uid',
    'TN01AB1234', 'Test Truck', 'Test Driver',
    'Chennai', 'Bangalore', NOW(),
    'advance_paid', 5000.00
);

-- Check if it worked
SELECT * FROM fleet_assignments WHERE al_number = 'TEST001';

-- Clean up test data
DELETE FROM fleet_assignments WHERE al_number = 'TEST001';
```

## Need Help?

If you still face issues:
1. Run the diagnostic script: `check_and_fix_fleet_assignments.php`
2. Check the response for specific error details
3. Verify your database credentials in `db_config.php`
4. Make sure the vehicles table exists first
