# Complete Vendor KYC System Flow

## 🎯 Overview

This document shows how all the pieces work together after uploading the files.

---

## 📊 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        VENDOR APP (Flutter)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Dashboard                 KYC Screen              Notifications │
│  ┌──────────┐             ┌──────────┐            ┌──────────┐  │
│  │ 🔔 Badge │────────────▶│ Submit   │───────────▶│ View All │  │
│  │ (Red)    │             │ KYC Form │            │ Notifs   │  │
│  └──────────┘             └──────────┘            └──────────┘  │
│       │                         │                       │        │
│       │                         │                       │        │
└───────┼─────────────────────────┼───────────────────────┼────────┘
        │                         │                       │
        │ GET unread count        │ POST form + files     │ GET notifications
        │                         │                       │
        ▼                         ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SERVER (PHP + MySQL)                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  get_notifications.php    upload_kyc_documents.php              │
│  ┌──────────────────┐    ┌────────────────────────┐            │
│  │ Returns:         │    │ 1. Save files to:      │            │
│  │ - notifications  │    │    /uploads/vendor_    │            │
│  │ - unread_count   │    │    kyc_documents/{uid}/│            │
│  └──────────────────┘    │                        │            │
│                          │ 2. Insert to DB:       │            │
│  mark_notification_      │    vendor_kyc table    │            │
│  read.php                │                        │            │
│  ┌──────────────────┐    │ 3. Create notification:│            │
│  │ Marks as read    │◀───│    "KYC Submitted"     │            │
│  └──────────────────┘    └────────────────────────┘            │
│                                                                  │
│  serve_kyc_image.php                                            │
│  ┌──────────────────────────────────────────────┐              │
│  │ Serves images from:                          │              │
│  │ /uploads/vendor_kyc_documents/{uid}/{file}   │              │
│  └──────────────────────────────────────────────┘              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ Admin actions
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN PANEL (HTML + JS)                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  admin_kyc_panel.html                                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                                                           │  │
│  │  KYC Status: SUBMITTED                                    │  │
│  │  ┌─────────────┐  ┌─────────────┐                        │  │
│  │  │ ✓ Approve   │  │ ✗ Reject    │                        │  │
│  │  └─────────────┘  └─────────────┘                        │  │
│  │                                                           │  │
│  │  KYC Status: VERIFIED                                     │  │
│  │  ┌─────────────┐                                          │  │
│  │  │ ⚠️ Revoke   │  ← NEW! This was missing                │  │
│  │  └─────────────┘                                          │  │
│  │                                                           │  │
│  │  KYC Status: REJECTED                                     │  │
│  │  ┌─────────────┐                                          │  │
│  │  │ ✓ Re-Approve│                                          │  │
│  │  └─────────────┘                                          │  │
│  │                                                           │  │
│  │  Documents:                                               │  │
│  │  📄 Aadhaar  📄 PAN  📷 Photo  🏦 Bank                   │  │
│  │  (Click to view images)                                   │  │
│  │                                                           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ POST to update_kyc_status.php
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    update_kyc_status.php                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Update vendor_kyc table:                                    │
│     - kyc_status = 'verified' OR 'rejected'                     │
│     - verified_at = NOW() OR NULL                               │
│     - rejection_reason = reason (if rejected)                   │
│                                                                  │
│  2. Create notification:                                        │
│     - kyc_approved: "KYC Verified Successfully"                 │
│     - kyc_rejected: "KYC Rejected" or "KYC Revoked"            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Complete KYC Flow

### 1️⃣ Vendor Submits KYC

```
Vendor App (kyc_verification_screen.dart)
    │
    │ User fills form + uploads documents
    │
    ▼
POST to upload_kyc_documents.php
    │
    ├─ Save files to: /uploads/vendor_kyc_documents/{uid}/
    │  - aadhaar_1234567890.jpg
    │  - pan_1234567890.jpg
    │  - photo_1234567890.jpg
    │  - bank_account_photo_1234567890.jpg
    │
    ├─ Insert/Update vendor_kyc table:
    │  - firebase_uid
    │  - account_type (individual/business)
    │  - name, email, phone
    │  - aadhaar_number, pan_number
    │  - aadhaar_doc, pan_doc, photo_doc, bank_account_photo
    │  - kyc_status = 'submitted'
    │
    └─ Create notification:
       - type: 'kyc_submitted'
       - title: "📋 KYC Submitted Successfully"
       - message: "Your documents are under review..."
    │
    ▼
Response: { status: 'success', uploaded_files: {...} }
    │
    ▼
App shows: "Verification Under Review" ✅
Dashboard shows: "KYC Under Review" banner ✅
My Fleet shows: "KYC Under Review" banner ✅
Notification badge: Shows "1" (unread) ✅
```

### 2️⃣ Admin Reviews KYC

```
Admin opens: admin_kyc_panel.html
    │
    ├─ Loads all KYC submissions from get_all_kyc.php
    │
    ├─ Filters by status: "submitted" (default)
    │
    ├─ Shows KYC card with:
    │  - Vendor name, email, phone
    │  - Aadhaar, PAN numbers
    │  - Document links (click to view)
    │  - Approve/Reject buttons
    │
    ▼
Admin clicks document link:
    │
    ▼
Opens: serve_kyc_image.php?uid={uid}&file={filename}
    │
    ├─ Reads file from: /uploads/vendor_kyc_documents/{uid}/{filename}
    │
    └─ Serves image with correct Content-Type
    │
    ▼
Image displays in new tab ✅
```

### 3️⃣ Admin Approves KYC

```
Admin clicks: "✓ Approve" button
    │
    ├─ Confirms action
    │
    ▼
POST to update_kyc_status.php
    {
      firebase_uid: "...",
      kyc_status: "verified"
    }
    │
    ├─ Update vendor_kyc:
    │  - kyc_status = 'verified'
    │  - verified_at = NOW()
    │  - rejection_reason = NULL
    │
    └─ Create notification:
       - type: 'kyc_approved'
       - title: "✅ KYC Verified Successfully!"
       - message: "You can now add vehicles..."
    │
    ▼
Response: { status: 'success', kyc_status: 'verified' }
    │
    ▼
Admin panel:
  - Status badge changes to green "VERIFIED" ✅
  - Orange "⚠️ Revoke" button appears ✅
  - Approve/Reject buttons disappear ✅

Vendor app:
  - Notification badge shows "2" (new notification) ✅
  - Dashboard: KYC banner disappears ✅
  - My Fleet: Can now add vehicles ✅
  - Notifications: Shows "KYC Verified Successfully" ✅
```

### 4️⃣ Admin Revokes KYC (NEW!)

```
Admin clicks: "⚠️ Revoke" button (for verified KYC)
    │
    ├─ Prompt appears: "Enter revoke reason:"
    │  Default: "KYC verification revoked by admin"
    │
    ├─ Admin enters reason
    │
    ├─ Confirms action
    │
    ▼
POST to update_kyc_status.php
    {
      firebase_uid: "...",
      kyc_status: "rejected",
      rejection_reason: "Documents expired"
    }
    │
    ├─ Update vendor_kyc:
    │  - kyc_status = 'rejected'
    │  - verified_at = NULL
    │  - rejection_reason = "Documents expired"
    │
    └─ Create notification:
       - type: 'kyc_rejected'
       - title: "❌ KYC Revoked"
       - message: "Reason: Documents expired. Please resubmit."
    │
    ▼
Response: { status: 'success', kyc_status: 'rejected' }
    │
    ▼
Admin panel:
  - Status badge changes to red "REJECTED" ✅
  - Green "✓ Re-Approve" button appears ✅
  - Revoke button disappears ✅

Vendor app:
  - Notification badge shows "3" (new notification) ✅
  - My Fleet: Shows "KYC Verification Failed" banner ✅
  - Notifications: Shows "KYC Revoked" with reason ✅
  - Can re-submit KYC ✅
```

### 5️⃣ Vendor Views Notifications

```
Vendor clicks: 🔔 notification icon (with red badge)
    │
    ▼
Opens: notifications_screen.dart
    │
    ├─ Immediately marks all as read:
    │  POST to mark_notification_read.php
    │  { firebase_uid: "...", mark_all: true }
    │
    ├─ Loads notifications:
    │  GET from get_notifications.php
    │  { firebase_uid: "..." }
    │
    ▼
Response:
    {
      status: 'success',
      notifications: [
        {
          type: 'kyc_rejected',
          title: '❌ KYC Revoked',
          message: 'Reason: Documents expired...',
          created_at: '2024-01-15 10:30:00',
          is_read: 1
        },
        {
          type: 'kyc_approved',
          title: '✅ KYC Verified Successfully!',
          message: 'You can now add vehicles...',
          created_at: '2024-01-14 15:20:00',
          is_read: 1
        },
        {
          type: 'kyc_submitted',
          title: '📋 KYC Submitted Successfully',
          message: 'Your documents are under review...',
          created_at: '2024-01-14 14:00:00',
          is_read: 1
        }
      ],
      unread_count: 0
    }
    │
    ▼
Screen shows:
  - Beautiful gradient cards for each notification ✅
  - Color-coded by type (green/red/blue) ✅
  - Time ago display ("2 hours ago") ✅
  - KYC badge for KYC notifications ✅
  - All marked as read ✅

Dashboard:
  - Red badge disappears (unread = 0) ✅
```

---

## 🎨 Notification Badge Behavior

```
┌─────────────────────────────────────────────────────────────┐
│                    Dashboard Header                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Hello, Vendor 👋                          🔔               │
│  Welcome to TRUCK UNION                   ┌──┐              │
│                                           │  │              │
│                                           └──┘              │
│                                            ↑                │
│                                            │                │
│                                   No unread notifications   │
│                                                              │
└─────────────────────────────────────────────────────────────┘

After new notification arrives:

┌─────────────────────────────────────────────────────────────┐
│                    Dashboard Header                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Hello, Vendor 👋                          🔔               │
│  Welcome to TRUCK UNION                   ┌──┐              │
│                                           │  │ ⭕ 1         │
│                                           └──┘              │
│                                            ↑                │
│                                            │                │
│                                   1 unread notification     │
│                                   (Red badge with number)   │
│                                                              │
└─────────────────────────────────────────────────────────────┘

After opening notifications screen:

┌─────────────────────────────────────────────────────────────┐
│                    Dashboard Header                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Hello, Vendor 👋                          🔔               │
│  Welcome to TRUCK UNION                   ┌──┐              │
│                                           │  │              │
│                                           └──┘              │
│                                            ↑                │
│                                            │                │
│                                   Badge disappears          │
│                                   (All marked as read)      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 File Upload Locations

```
Server Directory Structure:
/home/royaldxd/crm.abra-logistic.com/
│
├── serve_kyc_image.php ← Upload this
│
├── admin_kyc_panel.html ← Upload this
│
├── api1/
│   └── vendor/
│       ├── get_notifications.php ← Upload this
│       ├── mark_notification_read.php ← Upload this
│       ├── create_notification.php ← Upload this
│       ├── upload_kyc_documents.php ← Verify exists
│       ├── update_kyc_status.php ← Verify exists
│       └── get_all_kyc.php ← Verify exists
│
└── uploads/
    └── vendor_kyc_documents/
        └── {firebase_uid}/
            ├── aadhaar_1234567890.jpg
            ├── pan_1234567890.jpg
            ├── photo_1234567890.jpg
            └── bank_account_photo_1234567890.jpg
```

---

## ✅ Success Checklist

After uploading all files, verify:

- [ ] **Images**: Click document links in admin panel → Images display
- [ ] **Notifications**: Open app → Red badge shows on notification icon
- [ ] **Revoke**: Open admin panel → Orange revoke button shows for verified KYCs
- [ ] **Bulk Upload**: Open My Fleet → Only single "Add Vehicle" card shows
- [ ] **End-to-End**: Submit KYC → Approve → Revoke → Re-submit (full cycle works)

---

## 🎯 Key Points

1. **All code is already fixed** - Just need to upload files to server
2. **Revoke works like customer KYC** - Changes verified → rejected (not to submitted)
3. **Notification badge updates automatically** - Shows unread count in real-time
4. **Images serve from correct path** - `/uploads/vendor_kyc_documents/` (not `/uploads/kyc_documents/`)
5. **Bulk upload removed** - Only single vehicle add option remains

---

**Status**: Ready for deployment ✅
**Action Required**: Upload 6 files to server + clear browser cache
