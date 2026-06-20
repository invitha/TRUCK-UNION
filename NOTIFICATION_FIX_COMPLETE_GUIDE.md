# 🔔 Notification System - Complete Fix Guide

## 🔴 Your Error:

```
DioException [connection error]: The XMLHttpRequest onError callback was called
```

**This means**: The notification PHP files are **NOT on the server yet!**

---

## 📁 Step 1: Upload These 4 Files

### File 1: get_notifications.php
```
LOCAL:  vendor_app/server_php/api1_vendor/get_notifications.php
SERVER: /home/royaldxd/crm.abra-logistic.com/api1/vendor/get_notifications.php
```

### File 2: mark_notification_read.php
```
LOCAL:  vendor_app/server_php/api1_vendor/mark_notification_read.php
SERVER: /home/royaldxd/crm.abra-logistic.com/api1/vendor/mark_notification_read.php
```

### File 3: create_notification.php
```
LOCAL:  vendor_app/server_php/api1_vendor/create_notification.php
SERVER: /home/royaldxd/crm.abra-logistic.com/api1/vendor/create_notification.php
```

### File 4: test_notifications.php (for testing)
```
LOCAL:  vendor_app/server_php/api1_vendor/test_notifications.php
SERVER: /home/royaldxd/crm.abra-logistic.com/api1/vendor/test_notifications.php
```

---

## 🧪 Step 2: Test After Upload

### Test 1: Check if files exist
Open in browser:
```
https://crm.abra-logistic.com/api1/vendor/get_notifications.php
```

**Expected result**: JSON response (not 404 error)
```json
{
  "status": "error",
  "message": "Firebase UID required",
  "notifications": [],
  "unread_count": 0
}
```

If you see this JSON, the file is uploaded correctly! ✅

### Test 2: Run notification test
Open in browser:
```
https://crm.abra-logistic.com/api1/vendor/test_notifications.php
```

This will show:
- ✅ Database connection status
- ✅ Notifications table structure
- ✅ Notification counts
- ✅ Recent notifications
- ✅ Test create notification
- ✅ API endpoint links

---

## 🎯 Step 3: Test in App

After uploading files:

1. **Open vendor app**
2. **Go to dashboard**
3. **Check notification icon** (top right)
4. **You should see**:
   - No more CORS errors in console
   - Red badge with number (if unread notifications exist)
   - Badge disappears after opening notifications

---

## 📊 What Each File Does:

### get_notifications.php
- Returns list of notifications for a vendor
- Shows unread count
- Used by app to display notifications and badge

**API Call**:
```
POST https://crm.abra-logistic.com/api1/vendor/get_notifications.php
Body: { firebase_uid: "..." }
```

**Response**:
```json
{
  "status": "success",
  "notifications": [
    {
      "id": 1,
      "type": "kyc_approved",
      "title": "KYC Verified Successfully",
      "message": "You can now add vehicles...",
      "is_read": 0,
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "unread_count": 1
}
```

### mark_notification_read.php
- Marks notifications as read
- Can mark single notification or all notifications
- Used when vendor opens notification screen

**API Call**:
```
POST https://crm.abra-logistic.com/api1/vendor/mark_notification_read.php
Body: { firebase_uid: "...", mark_all: true }
```

**Response**:
```json
{
  "status": "success",
  "message": "All notifications marked as read"
}
```

### create_notification.php
- Helper function to create notifications
- Used by other PHP files (upload_kyc_documents.php, update_kyc_status.php)
- Not called directly by app

**Usage in PHP**:
```php
require_once('create_notification.php');
createNotification($con, $firebase_uid, 'kyc_approved', 'Title', 'Message');
```

---

## 🔄 Complete Notification Flow:

### 1. Vendor Submits KYC
```
App → upload_kyc_documents.php
     → Saves files
     → Inserts to vendor_kyc table
     → Calls createNotification()
     → Inserts to notifications table
     → Returns success
```

### 2. Admin Approves KYC
```
Admin Panel → vendor-verification.php
            → Updates vendor_kyc (status = verified)
            → Calls sendNotif()
            → Inserts to notifications table
            → Returns success
```

### 3. App Loads Dashboard
```
Dashboard → Calls get_notifications.php
         → Returns unread_count
         → Shows red badge with number
```

### 4. Vendor Opens Notifications
```
Notifications Screen → Calls mark_notification_read.php (mark_all = true)
                    → Updates notifications (is_read = 1)
                    → Calls get_notifications.php
                    → Returns all notifications
                    → Shows in list
                    → Badge disappears (unread = 0)
```

---

## 🚨 Common Issues & Solutions:

### Issue 1: Still getting CORS error after upload
**Solution**:
- Verify files uploaded to correct path: `/home/royaldxd/crm.abra-logistic.com/api1/vendor/`
- Check file permissions: `chmod 644 *.php`
- Test URL directly in browser

### Issue 2: JSON shows "Database connection failed"
**Solution**:
- Check database credentials in PHP files
- Should be:
  ```php
  $host = 'localhost';
  $dbname = 'royaldxd_abra_crm';
  $username = 'royaldxd_user';
  $password = 'meg_layout312';
  ```

### Issue 3: "Notifications table does not exist"
**Solution**:
- Run test_notifications.php - it will create the table automatically
- Or run this SQL:
  ```sql
  CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      firebase_uid VARCHAR(255) NOT NULL,
      type VARCHAR(50) NOT NULL,
      title VARCHAR(255) NOT NULL,
      message TEXT NOT NULL,
      is_read TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_firebase_uid (firebase_uid),
      INDEX idx_is_read (is_read)
  );
  ```

### Issue 4: Badge not showing even after upload
**Solution**:
- Check if notifications exist in database
- Run test_notifications.php to see notification counts
- Check app console for errors
- Verify Firebase UID is correct

---

## ✅ Verification Checklist:

- [ ] Uploaded get_notifications.php to server
- [ ] Uploaded mark_notification_read.php to server
- [ ] Uploaded create_notification.php to server
- [ ] Uploaded test_notifications.php to server
- [ ] Tested get_notifications.php in browser (shows JSON)
- [ ] Ran test_notifications.php (all tests pass)
- [ ] Opened app (no CORS errors in console)
- [ ] Submitted KYC (notification created)
- [ ] Approved KYC in admin panel (notification sent)
- [ ] Opened notifications in app (badge shows)
- [ ] Clicked notifications (badge disappears)

---

## 🎉 Expected Result:

After uploading all files:

1. ✅ No more CORS errors
2. ✅ Notifications load in app
3. ✅ Red badge shows unread count
4. ✅ Badge updates in real-time
5. ✅ Badge disappears after opening notifications
6. ✅ Notifications show with beautiful cards
7. ✅ KYC notifications work (submitted/approved/rejected)

---

## 📞 Still Having Issues?

If notifications still don't work after uploading:

1. **Check server error logs**:
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

2. **Check PHP error logs**:
   ```bash
   tail -f /var/log/php/error.log
   ```

3. **Test database connection**:
   ```
   https://crm.abra-logistic.com/api1/vendor/test_notifications.php
   ```

4. **Check app console**:
   - Open Chrome DevTools (F12)
   - Go to Console tab
   - Look for errors

---

**Status**: Ready to fix! Just upload the 4 files and test.
**Time**: ~5 minutes to upload and verify
**Result**: Notifications will work perfectly! 🎉
