# Revoke & Re-Submit Feature - COMPLETE

## New Features Added

### 1. ✅ Admin Can Revoke Verified KYC
**What**: Admin can revoke a previously approved KYC
**When**: After KYC is verified
**Result**: KYC status changes from `verified` → `submitted`
**Notification**: Vendor receives "⚠️ KYC Verification Revoked" notification

### 2. ✅ Admin Can Re-Approve Rejected KYC
**What**: Admin can approve a previously rejected KYC without vendor re-submitting
**When**: After KYC is rejected
**Result**: KYC status changes from `rejected` → `verified`
**Notification**: Vendor receives "✅ KYC Verified Successfully!" notification

### 3. ✅ Vendor Can Re-Submit After Rejection
**What**: Vendor can edit and re-submit KYC after rejection
**When**: After KYC is rejected
**Result**: KYC status changes from `rejected` → `submitted`
**Button**: Changes from "Submit for Verification" to "Re-Submit for Verification"

## Status Flow Diagram

```
┌─────────────┐
│ not_submitted│
└──────┬──────┘
       │ Vendor submits
       ↓
┌─────────────┐
│  submitted  │←──────────────┐
└──────┬──────┘               │
       │                      │ Admin revokes
       │ Admin approves       │
       ↓                      │
┌─────────────┐               │
│  verified   │───────────────┘
└─────────────┘

┌─────────────┐
│  submitted  │
└──────┬──────┘
       │ Admin rejects
       ↓
┌─────────────┐
│  rejected   │←──────────────┐
└──────┬──────┘               │
       │                      │ Vendor re-submits
       │ Admin re-approves    │
       │ (without re-submit)  │
       ↓                      │
┌─────────────┐               │
│  verified   │               │
└─────────────┘               │
       OR                     │
┌─────────────┐               │
│  submitted  │───────────────┘
└─────────────┘
```

## Admin Panel Changes

### Button Display Logic:

**Status: submitted**
- ✓ Approve (green)
- ✗ Reject (red)
- 👁 View Details (blue)

**Status: verified**
- ⚠️ Revoke (orange) ← NEW
- 👁 View Details (blue)

**Status: rejected**
- ✓ Re-Approve (green) ← NEW
- 👁 View Details (blue)

### Button Actions:

| Button | Action | Status Change | Notification |
|--------|--------|---------------|--------------|
| Approve | Verify KYC | submitted → verified | ✅ KYC Verified Successfully! |
| Reject | Reject KYC | submitted → rejected | ❌ KYC Verification Failed |
| Revoke | Revoke verification | verified → submitted | ⚠️ KYC Verification Revoked |
| Re-Approve | Approve without re-submit | rejected → verified | ✅ KYC Verified Successfully! |

## Vendor App Changes

### KYC Verification Screen:

**Status: submitted**
- Form disabled
- Button: "Verification Pending" (disabled)
- Banner: "⏳ Verification in Progress"

**Status: verified**
- Form disabled
- Button: "Verification Pending" (disabled)
- Banner: "✅ Verified"

**Status: rejected**
- Form enabled ← CHANGED
- Button: "Re-Submit for Verification" (enabled) ← CHANGED
- Banner: "❌ Verification Failed"
- User can edit all fields and re-submit

### Notification Types:

| Type | Icon | Color | Title |
|------|------|-------|-------|
| kyc_submitted | upload_file | Blue | 📋 KYC Submitted Successfully |
| kyc_approved | check_circle | Green | ✅ KYC Verified Successfully! |
| kyc_rejected | cancel | Red | ❌ KYC Verification Failed |
| kyc_revoked | warning_amber | Orange | ⚠️ KYC Verification Revoked |

## Use Cases

### Use Case 1: Admin Revokes Verified KYC
```
1. Vendor has verified KYC
2. Admin finds issue with documents
3. Admin clicks "⚠️ Revoke" button
4. Confirms: "Are you sure you want to REVOKE this KYC verification?"
5. Status changes: verified → submitted
6. Vendor receives notification: "⚠️ KYC Verification Revoked"
7. Vendor sees "⏳ Verification in Progress" banner
8. Admin can now approve or reject again
```

### Use Case 2: Admin Re-Approves Rejected KYC
```
1. Vendor's KYC was rejected
2. Vendor contacts admin with clarification
3. Admin reviews and decides to approve without re-submission
4. Admin clicks "✓ Re-Approve" button
5. Confirms: "Are you sure you want to APPROVE this KYC?"
6. Status changes: rejected → verified
7. Vendor receives notification: "✅ KYC Verified Successfully!"
8. Vendor can now add vehicles
```

### Use Case 3: Vendor Re-Submits After Rejection
```
1. Vendor's KYC was rejected
2. Vendor receives notification with reason
3. Vendor opens KYC screen
4. Sees "❌ Verification Failed" banner
5. Form is enabled - can edit all fields
6. Uploads new/corrected documents
7. Clicks "Re-Submit for Verification" button
8. Status changes: rejected → submitted
9. Vendor receives notification: "📋 KYC Submitted Successfully"
10. Admin can now review and approve/reject
```

## Files Modified

### PHP Files (Server):
1. **vendor_app/server_php/api1_vendor/update_kyc_status.php**
   - Added support for `submitted` status (revoke)
   - Added notification for revoke action
   - Validates 3 statuses: verified, rejected, submitted

### HTML Files (Admin Panel):
2. **vendor_app/admin_kyc_panel.html**
   - Added "⚠️ Revoke" button for verified KYCs
   - Added "✓ Re-Approve" button for rejected KYCs
   - Added CSS for `.btn-revoke` (orange button)
   - Updated confirmation messages

### Dart Files (App):
3. **vendor_app/lib/screens/vendor/kyc_verification_screen.dart**
   - Enabled form editing for rejected status
   - Changed button text to "Re-Submit for Verification" when rejected
   - Button enabled for rejected status

4. **vendor_app/lib/screens/vendor/notifications_screen.dart**
   - Added `kyc_revoked` notification type
   - Orange color and warning icon for revoked notifications

## API Endpoint

### POST /api1/vendor/update_kyc_status.php

**Request (Revoke):**
```json
{
  "firebase_uid": "user_firebase_uid",
  "kyc_status": "submitted"
}
```

**Request (Re-Approve):**
```json
{
  "firebase_uid": "user_firebase_uid",
  "kyc_status": "verified"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "KYC submitted successfully",
  "kyc_status": "submitted"
}
```

## Database Changes

No schema changes required. Uses existing `kyc_status` column with values:
- `not_submitted`
- `submitted`
- `verified`
- `rejected`

## Testing Checklist

### Test Revoke Feature:
- [ ] Admin approves KYC
- [ ] Vendor sees "✅ Verified" banner
- [ ] Admin clicks "⚠️ Revoke" button
- [ ] Confirms revoke action
- [ ] Status changes to "submitted"
- [ ] Vendor receives "⚠️ KYC Verification Revoked" notification
- [ ] Vendor sees "⏳ Verification in Progress" banner
- [ ] Admin can approve or reject again

### Test Re-Approve Feature:
- [ ] Admin rejects KYC
- [ ] Vendor sees "❌ Verification Failed" banner
- [ ] Admin clicks "✓ Re-Approve" button
- [ ] Confirms approve action
- [ ] Status changes to "verified"
- [ ] Vendor receives "✅ KYC Verified Successfully!" notification
- [ ] Vendor can add vehicles

### Test Re-Submit Feature:
- [ ] Admin rejects KYC with reason
- [ ] Vendor opens KYC screen
- [ ] Form is enabled (can edit)
- [ ] Button shows "Re-Submit for Verification"
- [ ] Vendor edits documents
- [ ] Clicks re-submit button
- [ ] Status changes to "submitted"
- [ ] Vendor receives "📋 KYC Submitted Successfully" notification
- [ ] Admin can review again

## Files to Upload

### SERVER FILES:
1. `vendor_app/server_php/api1_vendor/update_kyc_status.php` (MODIFIED)
2. `vendor_app/admin_kyc_panel.html` (MODIFIED)

Upload to:
- `/home/royaldxd/crm.abra-logistic.com/api1/vendor/update_kyc_status.php`
- `/home/royaldxd/crm.abra-logistic.com/admin_kyc_panel.html`

### APP FILES (Rebuild Required):
1. `vendor_app/lib/screens/vendor/kyc_verification_screen.dart` (MODIFIED)
2. `vendor_app/lib/screens/vendor/notifications_screen.dart` (MODIFIED)

Run: `flutter build apk`

## Summary

**Revoke Feature**: ✅ Admin can revoke verified KYC
**Re-Approve Feature**: ✅ Admin can approve rejected KYC without re-submission
**Re-Submit Feature**: ✅ Vendor can edit and re-submit rejected KYC
**Notifications**: ✅ All actions create appropriate notifications
**Status Flow**: ✅ Flexible workflow for all scenarios

---

**Status**: ✅ COMPLETE
**Ready for Testing**: YES
**Rebuild Required**: YES
