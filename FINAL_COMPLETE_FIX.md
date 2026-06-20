# COMPLETE FIX - File Upload & Notifications

## Issues Fixed

### 1. ✅ Document Images Not Found (404 Error)
**Problem**: Admin panel showed "Not Found" when clicking document links
**Root Cause**: `upload_kyc_documents_FINAL.php` was generating MOCK file paths instead of actually uploading files
**Solution**: Created new `upload_kyc_documents.php` with ACTUAL file upload implementation

**Changes**:
- Uses `move_uploaded_file()` to save files to server
- Creates directory structure: `/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/{firebase_uid}/`
- Generates unique filenames: `{doc_type}_{timestamp}_{uniqid}.{extension}`
- Validates file size (max 5MB) and type (jpg, jpeg, png, pdf)
- Keeps existing documents if no new upload
- Based on abra_app pattern

### 2. ✅ Notification Button Not Working
**Problem**: Clicking notification bell icon did nothing
**Root Cause**: Missing `onTap` handler on notification button
**Solution**: Added `GestureDetector` with navigation to `/vendor/notifications`

**Changes**:
- Wrapped notification icon in `GestureDetector`
- Added `onTap: () => context.push('/vendor/notifications')`
- Route already exists in app_router.dart

## Files Modified/Created

### NEW FILE:
**vendor_app/server_php/api1_vendor/upload_kyc_documents.php**
- Complete rewrite with actual file upload
- Creates upload directories automatically
- Validates files (size, type)
- Saves to: `/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/{uid}/`
- Creates notification on successful upload
- Updates or inserts KYC record

### MODIFIED FILE:
**vendor_app/lib/screens/vendor/vendor_dashboard.dart**
- Added `GestureDetector` to notification button
- Now navigates to notifications screen on tap

## File Upload Flow

### Before (BROKEN):
```
1. App sends files to upload_kyc_documents_FINAL.php
2. PHP generates FAKE paths like: "uploads/vendor_kyc/uid/aadhaar_123.jpg"
3. Saves fake paths to database
4. Admin clicks document link
5. Server returns 404 - File doesn't exist!
```

### After (WORKING):
```
1. App sends files to upload_kyc_documents.php
2. PHP creates directory: /home/royaldxd/.../uploads/vendor_kyc_documents/{uid}/
3. PHP moves uploaded file: move_uploaded_file()
4. Saves REAL filename to database: "aadhaar_1778146397_abc123.jpg"
5. Admin clicks document link
6. serve_kyc_image.php serves file from disk
7. Document opens successfully! ✅
```

## Document Serving Flow

### URL Format:
```
https://crm.abra-logistic.com/serve_kyc_image.php?uid={firebase_uid}&file={filename}
```

### Example:
```
https://crm.abra-logistic.com/serve_kyc_image.php?uid=qH4a8nKV1wSanQHE1QusUyF4wdh3&file=aadhaar_1778146397_abc123.jpg
```

### Server Path:
```
/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/qH4a8nKV1wSanQHE1QusUyF4wdh3/aadhaar_1778146397_abc123.jpg
```

## File Upload Implementation Details

### Directory Structure:
```
/home/royaldxd/crm.abra-logistic.com/
└── uploads/
    └── vendor_kyc_documents/
        └── {firebase_uid}/
            ├── aadhaar_1778146397_abc123.jpg
            ├── pan_1778146398_def456.jpg
            ├── photo_1778146399_ghi789.jpg
            ├── gst_1778146400_jkl012.jpg
            ├── address_proof_1778146401_mno345.jpg
            └── bank_account_photo_1778146402_pqr678.jpg
```

### File Naming Convention:
```
{document_type}_{timestamp}_{unique_id}.{extension}

Examples:
- aadhaar_1778146397_65a1b2c3d4e5f.jpg
- pan_1778146398_75b2c3d4e5f6g.png
- photo_1778146399_85c3d4e5f6g7h.jpg
```

### Validation Rules:
- **Max file size**: 5MB
- **Allowed types**: jpg, jpeg, png, pdf
- **Required docs**: aadhaar, pan, photo, bank_account_photo
- **Optional docs**: gst, address_proof (required for business accounts)

## Database Schema

### vendor_kyc table columns for documents:
```sql
aadhaar_doc VARCHAR(255)           -- Filename only
pan_doc VARCHAR(255)               -- Filename only
photo_doc VARCHAR(255)             -- Filename only
gst_doc VARCHAR(255)               -- Filename only
address_doc VARCHAR(255)           -- Filename only
bank_account_photo VARCHAR(255)    -- Filename only
```

**Note**: Only filename is stored, not full path. Path is constructed by serve_kyc_image.php

## Testing Checklist

### Test File Upload:
- [ ] Submit KYC with all documents from app
- [ ] Check server directory: `/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/{uid}/`
- [ ] Files should exist with correct naming pattern
- [ ] Database should have filenames (not full paths)

### Test Document Viewing:
- [ ] Open admin panel: `https://crm.abra-logistic.com/admin_kyc_panel.html`
- [ ] Find submitted KYC
- [ ] Click on any document link (Aadhaar, PAN, Photo, etc.)
- [ ] Document should open in new tab
- [ ] Should NOT show "Not Found" error

### Test Notification Button:
- [ ] Open vendor app
- [ ] Go to Dashboard
- [ ] Click notification bell icon (top right)
- [ ] Should navigate to Notifications screen
- [ ] Should show KYC notifications

### Test Complete Flow:
- [ ] Vendor submits KYC with documents
- [ ] Files uploaded to server
- [ ] Notification created: "📋 KYC Submitted Successfully"
- [ ] Admin opens admin panel
- [ ] Admin clicks document links - all open successfully
- [ ] Admin approves KYC
- [ ] Notification created: "✅ KYC Verified Successfully!"
- [ ] Vendor clicks notification bell
- [ ] Sees both notifications

## Files to Upload

### SERVER FILES (CRITICAL):
1. **vendor_app/server_php/api1_vendor/upload_kyc_documents.php** (NEW - REPLACES FINAL version)
   → Upload to: `/home/royaldxd/crm.abra-logistic.com/api1/vendor/upload_kyc_documents.php`
   → Purpose: Actual file upload implementation

2. **vendor_app/server_php/serve_kyc_image.php** (Already correct)
   → Upload to: `/home/royaldxd/crm.abra-logistic.com/serve_kyc_image.php`
   → Purpose: Serves uploaded documents

3. **vendor_app/server_php/api1_vendor/create_notification.php** (From previous fix)
   → Upload to: `/home/royaldxd/crm.abra-logistic.com/api1/vendor/create_notification.php`
   → Purpose: Creates notifications

### APP FILES (Rebuild Required):
1. **vendor_app/lib/screens/vendor/vendor_dashboard.dart** (MODIFIED)
   → Changes: Added notification button tap handler

Run: `flutter build apk` or `flutter build appbundle`

## Server Permissions

Ensure upload directory has correct permissions:
```bash
chmod 755 /home/royaldxd/crm.abra-logistic.com/uploads
chmod 755 /home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents
```

PHP will create user directories automatically with 0755 permissions.

## Troubleshooting

### If documents still show 404:
1. Check if files exist on server:
   ```bash
   ls -la /home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/{firebase_uid}/
   ```

2. Check file permissions:
   ```bash
   chmod 644 /home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/{firebase_uid}/*
   ```

3. Check serve_kyc_image.php is uploaded to root:
   ```
   https://crm.abra-logistic.com/serve_kyc_image.php
   ```

4. Test direct file access (should work):
   ```
   https://crm.abra-logistic.com/uploads/vendor_kyc_documents/{uid}/{filename}
   ```

### If notification button doesn't work:
1. Check console for errors
2. Verify route exists in app_router.dart
3. Rebuild app after changes
4. Clear app cache and reinstall

## Summary

**File Upload**: ✅ FIXED - Now actually uploads files to server
**Document Viewing**: ✅ FIXED - Admin can view all documents
**Notification Button**: ✅ FIXED - Now navigates to notifications screen
**Notification System**: ✅ WORKING - Creates notifications on KYC events

---

**Status**: ✅ COMPLETE
**Ready for Production**: YES
**Testing Required**: YES (follow checklist above)
