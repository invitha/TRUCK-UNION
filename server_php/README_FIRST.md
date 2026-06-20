# 🚛 VENDOR KYC ADMIN PANEL - QUICK START

## 📍 File Location

The vendor verification admin panel is located at:
```
vendor_app/server_php/vendor-verification.php
```

## ⚡ Quick Setup (3 Steps)

### 1️⃣ Configure Database
Edit `db_config.php` and update:
```php
$db_username = 'royaldxd_abra';  // Your database username
$db_password = 'YOUR_PASSWORD';   // Your database password
$db_name = 'royaldxd_abra';       // Your database name
```

### 2️⃣ Create Table
1. Open phpMyAdmin
2. Select database `royaldxd_abra`
3. Go to SQL tab
4. Copy and paste content from `create_vendor_kyc_table.sql`
5. Click "Go"

### 3️⃣ Upload & Access
1. Upload `vendor-verification.php` and `db_config.php` to your server
2. Place them in the same directory as `customer-verification.php`
3. Access: `https://crm.abra-logistic.com/vendor-verification.php`

## ✅ What You Should See

If setup is correct, you'll see:
- Header: "🚛 TRUCK UNION - Vendor KYC Admin Panel"
- Filter tabs: Submitted / Verified / Rejected / Pending
- Message: "No submitted vendor KYC submissions found" (if no data yet)

## ❌ If You See HTTP 500 Error

**Most likely causes:**
1. Database credentials wrong in `db_config.php`
2. Table `vendor_kyc` not created in database
3. File uploaded to wrong location

**Fix:**
1. Double-check `db_config.php` credentials
2. Run the SQL script in phpMyAdmin
3. Check PHP error logs

## 📚 Full Documentation

See `SETUP_GUIDE.md` for detailed instructions and troubleshooting.

## 🎯 What This Does

This admin panel allows you to:
- View all vendor KYC submissions
- Approve/reject KYC with reasons
- Revoke verified KYC
- Delete records (admin only)
- View uploaded documents
- Send notifications to vendors

## 🔗 Related Files

- `vendor-verification.php` - Main admin panel
- `db_config.php` - Database configuration
- `create_vendor_kyc_table.sql` - Table creation script
- `check_kyc_exists.php` - Duplicate validation API
- `SETUP_GUIDE.md` - Detailed setup guide

---

**Need Help?** Check `SETUP_GUIDE.md` for troubleshooting steps.
