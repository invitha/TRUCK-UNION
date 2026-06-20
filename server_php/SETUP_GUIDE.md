# TRUCK UNION - Vendor KYC Admin Panel Setup Guide

## 🚀 Quick Start

The vendor-verification.php file is a **standalone admin panel** for managing vendor KYC submissions. It displays all vendor KYC records with approve/reject/revoke/delete actions.

## 📋 Prerequisites

- PHP 7.0 or higher
- MySQL database
- phpMyAdmin access
- Web server (Apache/Nginx)

---

## Step-by-Step Setup Instructions

### Step 1: Configure Database Connection

1. Open `db_config.php`
2. Update with your actual database credentials:
   ```php
   $db_host = 'localhost';              // Usually 'localhost'
   $db_username = 'royaldxd_abra';      // Your database username
   $db_password = 'your_password';      // Your database password
   $db_name = 'royaldxd_abra';          // Your database name
   ```

3. **IMPORTANT:** The database name is likely `royaldxd_abra` based on your abra_app setup

### Step 2: Create Database Table

1. Open **phpMyAdmin** → `https://your-server.com/phpmyadmin`
2. Select your database (`royaldxd_abra`) from the left sidebar
3. Click on the **SQL** tab at the top
4. Open `create_vendor_kyc_table.sql` file
5. Copy the **entire SQL script**
6. Paste it into the SQL query box
7. Click **Go** to execute
8. Verify the table was created:
   - Click on **Structure** tab
   - You should see `vendor_kyc` table with all columns

### Step 3: Upload Files to Server

Upload these files to your server at: `https://crm.abra-logistic.com/`

**Required files:**
- `vendor-verification.php` (the admin panel)
- `db_config.php` (with your credentials)
- `check_kyc_exists.php` (for duplicate validation)

**Upload location:** Same directory as `customer-verification.php` in abra_app

Example path: `/home/royaldxd/crm.abra-logistic.com/vendor-verification.php`

### Step 4: Access the Admin Panel

Open in your browser:
```
https://crm.abra-logistic.com/vendor-verification.php
```

**What you should see:**
- Header: "🚛 TRUCK UNION - Vendor KYC Admin Panel"
- Filter tabs: Submitted / Verified / Rejected / Pending
- List of vendor KYC submissions (if any exist)
- If no submissions: "No submitted vendor KYC submissions found"

**If you see HTTP 500 error:**
- Check Step 1: Database credentials in `db_config.php`
- Check Step 2: Table `vendor_kyc` exists in database
- Check PHP error logs for details

### Step 5: Test with Sample Data (Optional)

To test the admin panel, insert sample data:

1. Open phpMyAdmin → SQL tab
2. Run this query:
```sql
INSERT INTO `vendor_kyc` (
  `firebase_uid`, `account_type`, `name`, `email`, `phone`,
  `aadhaar_number`, `pan_number`, 
  `bank_account_name`, `bank_account_number`, `ifsc_code`,
  `kyc_status`
) VALUES (
  'test_vendor_123',
  'individual',
  'Test Vendor',
  'test@example.com',
  '9876543210',
  '123456789012',
  'ABCDE1234F',
  'Test Vendor',
  '1234567890123456',
  'ABCD0123456',
  'submitted'
);
```

3. Refresh `vendor-verification.php` - you should see the test record

---

## 🎯 Admin Panel Features

### Filter Tabs
- **Submitted** - New KYC submissions awaiting review
- **Verified** - Approved KYC records
- **Rejected** - Rejected KYC records
- **Pending** - Incomplete submissions

### Actions Available

**For Submitted KYC:**
- ✓ **Approve** - Verify the KYC (sends notification to vendor)
- ✗ **Reject** - Reject with reason (sends notification to vendor)

**For Verified KYC:**
- ⚠ **Revoke** - Revoke verification with reason

**For All KYC (Admin only):**
- 🗑 **Delete** - Permanently delete record (only Abishek can delete)

### Document Viewing
- Click on any document link to view/download
- Supported formats: JPG, PNG, PDF, WEBP
- Documents are served securely through the PHP file

---

## 🔒 Security Features

### Admin Access Control
Only authorized admins can delete records:
- Abishek Veeraswamy
- Abishek
- abishek

### Session-Based Authentication
The panel uses PHP sessions. Make sure you're logged into your CRM system.

### Secure File Serving
Documents are served through PHP (not direct URLs) with:
- Path sanitization
- File existence validation
- Proper content-type headers

---

## 📊 Database Table Structure

```sql
vendor_kyc table columns:
├── id (Primary Key)
├── firebase_uid (Unique)
├── account_type (individual/business)
├── name, email, phone
├── aadhaar_number (Unique)
├── pan_number (Unique)
├── bank_account_name
├── bank_account_number (Unique)
├── ifsc_code
├── bank_account_photo
├── company_name, gst_number (for business)
├── aadhaar_doc, pan_doc, photo_doc
├── gst_doc, address_doc
├── kyc_status (pending/submitted/verified/rejected)
├── rejection_reason
├── verified_at
├── created_at, updated_at
```

---

## 🐛 Troubleshooting

### HTTP ERROR 500
**Cause:** Database connection failed or table doesn't exist

**Solution:**
1. Check `db_config.php` credentials
2. Verify database name is correct
3. Run `create_vendor_kyc_table.sql` in phpMyAdmin
4. Check PHP error logs: `/home/royaldxd/logs/error_log`

### "Setup Required: vendor_kyc table missing"
**Cause:** Table not created in database

**Solution:**
1. Open phpMyAdmin
2. Select your database
3. Run the SQL script from `create_vendor_kyc_table.sql`

### "Database connection failed"
**Cause:** Wrong credentials in `db_config.php`

**Solution:**
1. Verify database username/password
2. Check if database exists
3. Test connection with phpMyAdmin

### Documents not loading
**Cause:** Upload directory doesn't exist or wrong path

**Solution:**
1. Create directory: `/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/`
2. Set permissions: `chmod 755 uploads/`
3. Check document paths in database

### No records showing
**Cause:** No KYC submissions yet or wrong status filter

**Solution:**
1. Check if table has data: `SELECT * FROM vendor_kyc;`
2. Try different filter tabs (Submitted/Verified/Rejected/Pending)
3. Insert test data (see Step 5 above)

---

## 📱 Integration with Flutter App

The Flutter app submits KYC through API endpoints. Make sure:

1. **API endpoints are configured** in `lib/services/api_service.dart`
2. **Upload directory exists** and is writable
3. **Notifications table exists** for sending approval/rejection notifications

---

## 🔄 Workflow

1. **Vendor submits KYC** through Flutter app
2. **KYC status** changes to `submitted`
3. **Admin opens** `vendor-verification.php`
4. **Admin reviews** documents and details
5. **Admin approves/rejects** with reason
6. **Vendor receives** notification in app
7. **If approved:** Vendor can add vehicles
8. **If rejected:** Vendor can resubmit with corrections

---

## 📞 Support

If you encounter issues:

1. **Check PHP error logs:** `/home/royaldxd/logs/error_log`
2. **Verify database:** Use phpMyAdmin to check table and data
3. **Test connection:** Create a simple test.php file:
   ```php
   <?php
   require_once('db_config.php');
   echo $conn ? "Connected!" : "Failed!";
   ?>
   ```
4. **Check file permissions:** Ensure PHP files are readable

---

**File Location:** `vendor_app/server_php/vendor-verification.php`  
**Last Updated:** May 2026  
**Version:** 1.0
