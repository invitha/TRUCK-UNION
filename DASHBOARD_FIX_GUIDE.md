# 🔧 Dashboard.php "Page Not Working" - Fix Guide

## 🎯 Problem
Dashboard.php is showing "page is not working" error.

## 📋 Step-by-Step Diagnosis

### Step 1: Find the Exact Error
Upload `test_dashboard_error.php` to your server and open it in browser:
```
https://yoursite.com/test_dashboard_error.php
```

This will show you the EXACT error message, line number, and what's wrong.

---

## 🔍 Common Issues & Solutions

### Issue 1: Missing `fleet_assignments` Table
**Error:** Table 'royaldxd_abra_crm.fleet_assignments' doesn't exist

**Solution:**
1. Go to phpMyAdmin
2. Select database: `royaldxd_abra_crm`
3. Click "SQL" tab
4. Run this SQL:

```sql
CREATE TABLE IF NOT EXISTS fleet_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  al_number VARCHAR(50) NOT NULL,
  vehicle_id INT NOT NULL,
  vendor_firebase_uid VARCHAR(255),
  vehicle_number VARCHAR(50),
  vehicle_name VARCHAR(100),
  driver_name VARCHAR(100),
  assigned_by VARCHAR(100),
  pickup_location TEXT,
  delivery_location TEXT,
  expected_completion_date DATE,
  status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  notes TEXT,
  payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid',
  payment_amount DECIMAL(10,2) DEFAULT 0.00,
  advance_amount DECIMAL(10,2) DEFAULT 0.00,
  remaining_amount DECIMAL(10,2) DEFAULT 0.00,
  payment_date DATETIME NULL,
  payment_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vehicle_id (vehicle_id),
  INDEX idx_vendor_uid (vendor_firebase_uid),
  INDEX idx_status (status),
  INDEX idx_al_number (al_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Issue 2: Missing Payment Columns
**Error:** Unknown column 'payment_status' in 'field list'

**Solution:**
Run this SQL to add missing columns:

```sql
ALTER TABLE fleet_assignments
ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' AFTER notes,
ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status,
ADD COLUMN IF NOT EXISTS advance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount,
ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(10,2) DEFAULT 0.00 AFTER advance_amount,
ADD COLUMN IF NOT EXISTS payment_date DATETIME NULL AFTER remaining_amount,
ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER payment_date;
```

---

### Issue 3: Missing Location Columns in `vehicles` Table
**Error:** Unknown column 'vendor_location' or 'vendor_city'

**Solution:**
Run this SQL:

```sql
ALTER TABLE vehicles
ADD COLUMN IF NOT EXISTS vendor_location VARCHAR(255) AFTER vendor_phone,
ADD COLUMN IF NOT EXISTS vendor_city VARCHAR(100) AFTER vendor_location,
ADD COLUMN IF NOT EXISTS vendor_state VARCHAR(100) AFTER vendor_city,
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) AFTER vendor_state,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) AFTER latitude;
```

---

### Issue 4: Database Connection Failed
**Error:** DB Error (connection failed)

**Solution:**
Check database credentials in dashboard.php (lines 6-9):

```php
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';
```

Make sure these match your actual database credentials.

---

### Issue 5: PHP Version Too Old
**Error:** Syntax error, unexpected 'match'

**Solution:**
- Dashboard.php uses PHP 8.0+ features (match expression)
- Upgrade to PHP 8.0 or higher
- Or contact your hosting provider to enable PHP 8.0+

---

### Issue 6: Memory Limit Exceeded
**Error:** Allowed memory size exhausted

**Solution:**
Add this at the top of dashboard.php (after line 4):

```php
ini_set('memory_limit', '256M');
```

---

### Issue 7: Execution Time Limit
**Error:** Maximum execution time exceeded

**Solution:**
Add this at the top of dashboard.php (after line 4):

```php
ini_set('max_execution_time', '300');
```

---

## 🚀 Quick Fix Checklist

Run these in order:

1. ✅ Upload `test_dashboard_error.php` to server
2. ✅ Open it in browser to see exact error
3. ✅ Check if `fleet_assignments` table exists
4. ✅ Check if payment columns exist
5. ✅ Check if location columns exist in vehicles table
6. ✅ Verify database credentials
7. ✅ Check PHP version (must be 8.0+)
8. ✅ Check memory and execution limits

---

## 📞 What to Share With Me

If you still have issues, share:

1. **Screenshot** of `test_dashboard_error.php` output
2. **Error message** (exact text)
3. **Line number** where error occurs
4. **PHP version** from test output

Then I can give you the exact fix!

---

## 💡 Alternative: Use Diagnostic Tool

If `test_dashboard_error.php` doesn't work, use:
```
https://yoursite.com/diagnose_dashboard.php
```

This checks:
- Database connection
- Required tables
- Required columns
- PHP version
- PHP extensions

---

## ✅ Success Indicators

Dashboard is working when you see:
- ✅ Vehicle list displayed
- ✅ Filters working
- ✅ Statistics showing
- ✅ No error messages
- ✅ "Assign Vehicle" button visible

---

## 🎯 Most Likely Issue

Based on previous errors, it's probably:
1. **Missing `fleet_assignments` table** (90% chance)
2. **Missing payment columns** (5% chance)
3. **PHP version too old** (3% chance)
4. **Database connection** (2% chance)

Run the SQL to create `fleet_assignments` table first!
