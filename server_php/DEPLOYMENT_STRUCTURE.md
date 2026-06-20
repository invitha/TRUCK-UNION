# 📁 VENDOR API DEPLOYMENT STRUCTURE

## Server Folder Structure

```
/home/royaldxd/crm.abra-logistic.com/
│
├── api1/
│   ├── customer/                    ← Already exists (customer app APIs)
│   │   ├── submit_kyc.php
│   │   ├── get_kyc_status.php
│   │   └── ...
│   │
│   └── vendor/                      ← CREATE THIS FOLDER (vendor app APIs)
│       ├── submit_kyc.php           ← Upload here
│       ├── get_kyc_status.php       ← Upload here
│       ├── check_kyc_exists.php     ← Upload here
│       ├── update_vendor_kyc_status.php
│       └── get_all_vendor_kyc.php
│
└── dashboard/
    ├── database.php                 ← Already exists
    ├── library.php                  ← Already exists
    ├── funciones.php                ← Already exists
    ├── customer-verification.php    ← Already exists (customer KYC admin)
    └── vendor-verification.php      ← Upload here (vendor KYC admin)
```

---

## 🎯 Step-by-Step Deployment

### Step 1: Create Vendor Folder

1. Go to cPanel File Manager
2. Navigate to: `/home/royaldxd/crm.abra-logistic.com/api1/`
3. Click "New Folder"
4. Name it: `vendor`
5. Click "Create"

### Step 2: Upload Vendor API Files

Upload these files to: `/home/royaldxd/crm.abra-logistic.com/api1/vendor/`

**Files to upload:**
- ✅ `submit_kyc.php`
- ✅ `get_kyc_status.php`
- ✅ `check_kyc_exists.php`
- ✅ `update_vendor_kyc_status.php`
- ✅ `get_all_vendor_kyc.php`

### Step 3: Upload Admin Panel

Upload this file to: `/home/royaldxd/crm.abra-logistic.com/dashboard/`

**File to upload:**
- ✅ `vendor-verification.php`

### Step 4: Create Database Table

1. Open phpMyAdmin
2. Select database: `royaldxd_abra`
3. Go to SQL tab
4. Run: `create_vendor_kyc_table.sql`

---

## 🔗 Final URLs

### Vendor API Endpoints (Flutter App Uses These)
```
POST https://crm.abra-logistic.com/api1/vendor/submit_kyc.php
POST https://crm.abra-logistic.com/api1/vendor/get_kyc_status.php
POST https://crm.abra-logistic.com/api1/vendor/check_kyc_exists.php
POST https://crm.abra-logistic.com/api1/vendor/update_vendor_kyc_status.php
POST https://crm.abra-logistic.com/api1/vendor/get_all_vendor_kyc.php
```

### Admin Panel
```
https://crm.abra-logistic.com/dashboard/vendor-verification.php
```

---

## ✅ Flutter App Configuration

The Flutter app is already configured correctly:

**In `lib/services/api_service.dart`:**
```dart
static const String baseUrl = 'https://crm.abra-logistic.com/api1';

// Vendor KYC endpoints
POST $baseUrl/vendor/submit_kyc.php          ✅ Correct
POST $baseUrl/vendor/get_kyc_status.php      ✅ Correct
POST $baseUrl/vendor/check_kyc_exists.php    ✅ Correct
```

No changes needed in Flutter app! Just deploy the PHP files.

---

## 📋 Deployment Checklist

### Database Setup
- [ ] Open phpMyAdmin
- [ ] Select database `royaldxd_abra`
- [ ] Run `create_vendor_kyc_table.sql`
- [ ] Verify `vendor_kyc` table exists

### Create Vendor Folder
- [ ] Go to cPanel File Manager
- [ ] Navigate to `/api1/`
- [ ] Create new folder: `vendor`
- [ ] Set permissions: 755

### Upload Vendor API Files
- [ ] Upload `submit_kyc.php` to `/api1/vendor/`
- [ ] Upload `get_kyc_status.php` to `/api1/vendor/`
- [ ] Upload `check_kyc_exists.php` to `/api1/vendor/`
- [ ] Upload `update_vendor_kyc_status.php` to `/api1/vendor/`
- [ ] Upload `get_all_vendor_kyc.php` to `/api1/vendor/`
- [ ] Set file permissions: 644

### Upload Admin Panel
- [ ] Upload `vendor-verification.php` to `/dashboard/`
- [ ] Set file permissions: 644

### Test Deployment
- [ ] Test API: `https://crm.abra-logistic.com/api1/vendor/get_kyc_status.php`
- [ ] Test Admin: `https://crm.abra-logistic.com/dashboard/vendor-verification.php`
- [ ] Submit KYC from Flutter app
- [ ] Verify KYC appears in admin panel

---

## 🔍 Verification

### Test API Endpoint
Open in browser or Postman:
```
POST https://crm.abra-logistic.com/api1/vendor/get_kyc_status.php
Body: {"firebase_uid": "test123"}
```

Expected response:
```json
{
  "status": "success",
  "kyc_status": "not_submitted",
  "message": "No KYC found for this user"
}
```

### Test Admin Panel
Open in browser:
```
https://crm.abra-logistic.com/dashboard/vendor-verification.php
```

Expected:
- Navy blue background
- 3 tabs: Submitted, Verified, Rejected
- No HTTP 500 error

---

## 🎨 Why This Structure?

### Organized by App Type
```
api1/
├── customer/    ← Customer app APIs
└── vendor/      ← Vendor app APIs
```

### Benefits
✅ **Clear separation** - Customer and vendor APIs are separate
✅ **Easy maintenance** - Each app has its own folder
✅ **Scalable** - Can add more app types (driver/, admin/, etc.)
✅ **Consistent** - Follows same pattern as customer app

---

## 📞 Troubleshooting

### API Returns 404
**Cause:** Folder or file doesn't exist
**Fix:** 
1. Verify folder exists: `/api1/vendor/`
2. Verify files are uploaded
3. Check file names are exact (case-sensitive)

### API Returns 500
**Cause:** Database connection failed
**Fix:**
1. Verify `database.php` exists in parent directories
2. Check database credentials
3. Verify table `vendor_kyc` exists

### Admin Panel Returns 500
**Cause:** Missing database files
**Fix:**
1. Verify `database.php`, `library.php`, `funciones.php` exist in `/dashboard/`
2. Check database connection

---

## 📝 Summary

**Folder to create:** `/api1/vendor/`

**Files to upload:**
- 5 files → `/api1/vendor/`
- 1 file → `/dashboard/`

**Database:**
- Run SQL script in phpMyAdmin

**Flutter app:**
- No changes needed! Already configured correctly.

---

**Last Updated:** May 6, 2026  
**Status:** ✅ Ready to Deploy
