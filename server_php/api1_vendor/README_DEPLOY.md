# 🚀 VENDOR API FILES - READY TO DEPLOY

## 📁 These 5 Files Go in: `/api1/vendor/`

### ✅ Files in This Folder

1. **submit_kyc.php** - Submit/update vendor KYC
2. **get_kyc_status.php** - Get vendor KYC status
3. **check_kyc_exists.php** - Check duplicate Aadhaar/PAN/Bank
4. **get_notifications.php** - Get vendor notifications
5. **mark_notification_read.php** - Mark notifications as read

---

## 📍 Upload Location

```
/home/royaldxd/crm.abra-logistic.com/api1/vendor/
```

### Step-by-Step:

1. **Create folder** (if doesn't exist):
   - Go to cPanel File Manager
   - Navigate to: `/home/royaldxd/crm.abra-logistic.com/api1/`
   - Click "New Folder"
   - Name: `vendor`

2. **Upload all 5 files** to the `vendor` folder

3. **Set permissions**: 644 for all files

---

## 🔗 Final URLs

After upload, these URLs will work:

```
https://crm.abra-logistic.com/api1/vendor/submit_kyc.php
https://crm.abra-logistic.com/api1/vendor/get_kyc_status.php
https://crm.abra-logistic.com/api1/vendor/check_kyc_exists.php
https://crm.abra-logistic.com/api1/vendor/get_notifications.php
https://crm.abra-logistic.com/api1/vendor/mark_notification_read.php
```

---

## ✨ Features Included

### 1. submit_kyc.php
- ✅ Receives KYC from Flutter app
- ✅ Validates all required fields
- ✅ Stores in `vendor_kyc` table
- ✅ Sends notification to vendor
- ✅ Handles both new and update submissions

### 2. get_kyc_status.php
- ✅ Returns current KYC status
- ✅ Returns all KYC details
- ✅ Returns rejection reason (if rejected)
- ✅ Returns verification date (if verified)

### 3. check_kyc_exists.php
- ✅ Checks Aadhaar uniqueness
- ✅ Checks PAN uniqueness
- ✅ Checks Bank Account uniqueness
- ✅ Excludes current user from check

### 4. get_notifications.php
- ✅ Returns all notifications for vendor
- ✅ Supports pagination (limit/offset)
- ✅ Returns unread count
- ✅ Ordered by newest first

### 5. mark_notification_read.php
- ✅ Mark single notification as read
- ✅ Mark all notifications as read
- ✅ Validates user ownership

---

## 🔔 Notification Types

These notifications are automatically sent:

| Event | Type | Title | Message |
|-------|------|-------|---------|
| KYC Submitted | `kyc_submitted` | ✅ KYC Submitted | Your KYC has been submitted for review... |
| KYC Resubmitted | `kyc_resubmitted` | 📋 KYC Resubmitted | Your KYC has been resubmitted for review... |
| KYC Approved | `kyc_approved` | ✓ KYC Verified | Your vendor KYC has been approved... |
| KYC Rejected | `kyc_rejected` | KYC Rejected | Your KYC was rejected. Reason: ... |
| KYC Revoked | `kyc_rejected` | KYC Revoked | Your KYC verification has been revoked... |

---

## 🗄️ Database Requirements

### Tables Needed:

1. **vendor_kyc** - Stores vendor KYC data
   - Run: `create_vendor_kyc_table.sql`

2. **notifications** - Stores notifications
   - Should already exist (shared with customer app)
   - If not, create with:
   ```sql
   CREATE TABLE IF NOT EXISTS `notifications` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `firebase_uid` VARCHAR(255) NOT NULL,
     `type` VARCHAR(50) NOT NULL,
     `title` VARCHAR(255) NOT NULL,
     `message` TEXT NOT NULL,
     `is_read` TINYINT(1) DEFAULT 0,
     `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     KEY `firebase_uid` (`firebase_uid`),
     KEY `is_read` (`is_read`),
     KEY `created_at` (`created_at`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

---

## 🧪 Testing

### Test 1: Get KYC Status
```bash
curl -X POST https://crm.abra-logistic.com/api1/vendor/get_kyc_status.php \
  -H "Content-Type: application/json" \
  -d '{"firebase_uid":"test123"}'
```

Expected:
```json
{
  "status": "success",
  "kyc_status": "not_submitted",
  "message": "No KYC found for this user"
}
```

### Test 2: Check Duplicates
```bash
curl -X POST https://crm.abra-logistic.com/api1/vendor/check_kyc_exists.php \
  -H "Content-Type: application/json" \
  -d '{
    "firebase_uid":"test123",
    "aadhaar_number":"123456789012",
    "pan_number":"ABCDE1234F"
  }'
```

Expected:
```json
{
  "status": "available",
  "message": "KYC details are available"
}
```

### Test 3: Get Notifications
```bash
curl -X POST https://crm.abra-logistic.com/api1/vendor/get_notifications.php \
  -H "Content-Type: application/json" \
  -d '{"firebase_uid":"test123"}'
```

Expected:
```json
{
  "status": "success",
  "notifications": [],
  "unread_count": 0,
  "total": 0
}
```

---

## 📱 Flutter App Integration

The Flutter app is already configured:

**In `lib/services/api_service.dart`:**
```dart
static const String baseUrl = 'https://crm.abra-logistic.com/api1';

// These will work automatically after upload:
POST $baseUrl/vendor/submit_kyc.php
POST $baseUrl/vendor/get_kyc_status.php
POST $baseUrl/vendor/check_kyc_exists.php
POST $baseUrl/vendor/get_notifications.php
POST $baseUrl/vendor/mark_notification_read.php
```

No changes needed in Flutter app!

---

## 🔒 Security Features

✅ **CORS enabled** - Flutter app can call APIs  
✅ **Input validation** - All inputs validated  
✅ **SQL injection prevention** - Prepared statements used  
✅ **Error logging** - Errors logged, not displayed  
✅ **Duplicate prevention** - Unique constraints enforced  
✅ **User isolation** - Users can only access their own data  

---

## 🐛 Troubleshooting

### API Returns 500 Error
**Fix:** Check that `database.php`, `library.php`, `funciones.php` exist in parent directory (`/api1/`)

### Notifications Not Appearing
**Fix:** Verify `notifications` table exists in database

### Duplicate Check Not Working
**Fix:** Verify `vendor_kyc` table has UNIQUE constraints on aadhaar_number, pan_number, bank_account_number

---

## ✅ Deployment Checklist

- [ ] Create folder: `/api1/vendor/`
- [ ] Upload all 5 PHP files
- [ ] Set file permissions: 644
- [ ] Verify `vendor_kyc` table exists
- [ ] Verify `notifications` table exists
- [ ] Test API endpoints
- [ ] Submit test KYC from Flutter app
- [ ] Verify notifications work

---

**Ready to deploy!** Just upload these 5 files to `/api1/vendor/` and you're done! 🎉
