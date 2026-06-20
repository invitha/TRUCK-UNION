# Revoke Feature - Matching Customer KYC Pattern

## Changes Made

Updated vendor KYC to work **exactly like customer KYC** in abra_app:

### Key Change:
**Revoke now changes status from `verified` → `rejected` (with reason)**

Previously, revoke was changing to `submitted`. Now it matches customer KYC behavior.

## How It Works Now

### 1. Revoke Button (Verified KYC)
- **Button**: ⚠️ Revoke (orange)
- **Action**: Prompts for revoke reason
- **Status Change**: `verified` → `rejected`
- **Database**: Sets `verified_at = NULL`, stores reason in `rejection_reason`
- **Notification**: "❌ KYC Verification Failed" with reason

### 2. Reject Button (Submitted KYC)
- **Button**: ✗ Reject (red)
- **Action**: Prompts for rejection reason
- **Status Change**: `submitted` → `rejected`
- **Database**: Sets `verified_at = NULL`, stores reason in `rejection_reason`
- **Notification**: "❌ KYC Verification Failed" with reason

### 3. Re-Approve Button (Rejected KYC)
- **Button**: ✓ Re-Approve (green)
- **Action**: Directly approves without prompt
- **Status Change**: `rejected` → `verified`
- **Database**: Sets `verified_at = NOW()`, clears `rejection_reason`
- **Notification**: "✅ KYC Verified Successfully!"

## Status Flow

```
┌─────────────┐
│ not_submitted│
└──────┬──────┘
       │ Vendor submits
       ↓
┌─────────────┐
│  submitted  │
└──────┬──────┘
       │
       ├─ Admin approves ──→ verified
       │
       └─ Admin rejects ───→ rejected

┌─────────────┐
│  verified   │
└──────┬──────┘
       │
       └─ Admin revokes ───→ rejected (with reason)

┌─────────────┐
│  rejected   │
└──────┬──────┘
       │
       ├─ Admin re-approves ──→ verified
       │
       └─ Vendor re-submits ──→ submitted
```

## Admin Panel Buttons

| Current Status | Buttons Available |
|----------------|-------------------|
| submitted | ✓ Approve \| ✗ Reject (prompts for reason) |
| verified | ⚠️ Revoke (prompts for reason) |
| rejected | ✓ Re-Approve |

## Comparison with Customer KYC

| Feature | Customer KYC (abra_app) | Vendor KYC (vendor_app) |
|---------|-------------------------|-------------------------|
| Revoke Action | verified → rejected | verified → rejected ✅ |
| Revoke Reason | Prompts for reason | Prompts for reason ✅ |
| Reject Action | submitted → rejected | submitted → rejected ✅ |
| Reject Reason | Prompts for reason | Prompts for reason ✅ |
| Re-Approve | rejected → verified | rejected → verified ✅ |
| Notification Type | kyc_rejected | kyc_rejected ✅ |

**Result**: ✅ **100% Match** - Vendor KYC now works exactly like Customer KYC

## Code Changes

### 1. update_kyc_status.php
- Removed `submitted` as valid status (revoke no longer uses it)
- Revoke now uses `rejected` status with reason
- Simplified notification logic (only 2 types: approved, rejected)

### 2. admin_kyc_panel.html
- Added `promptReject()` function - prompts for rejection reason
- Added `promptRevoke()` function - prompts for revoke reason
- Updated button onclick handlers to use prompt functions
- Both reject and revoke now send reason to backend

### 3. Removed from notifications_screen.dart
- Removed `kyc_revoked` notification type (no longer needed)
- Only uses `kyc_rejected` for both reject and revoke

## User Experience

### Admin Workflow:

**Scenario 1: Reject Submitted KYC**
1. Admin clicks "✗ Reject" button
2. Prompt appears: "Enter rejection reason:"
3. Admin enters: "Aadhaar photo is blurry"
4. Confirms action
5. Status changes to rejected
6. Vendor receives notification with reason

**Scenario 2: Revoke Verified KYC**
1. Admin clicks "⚠️ Revoke" button
2. Prompt appears: "Enter revoke reason:"
3. Admin enters: "Found duplicate documents"
4. Confirms action
5. Status changes to rejected
6. Vendor receives notification with reason

**Scenario 3: Re-Approve Rejected KYC**
1. Admin clicks "✓ Re-Approve" button
2. Confirms action (no reason needed)
3. Status changes to verified
4. Vendor receives success notification

### Vendor Experience:

**After Rejection (from either reject or revoke):**
- Sees "❌ Verification Failed" banner
- Notification shows rejection reason
- Form is enabled for editing
- Can upload new documents
- Button shows "Re-Submit for Verification"
- Re-submission changes status back to `submitted`

## Files Modified

1. **vendor_app/server_php/api1_vendor/update_kyc_status.php**
   - Removed `submitted` status support
   - Simplified to only handle `verified` and `rejected`
   - Both reject and revoke use `rejected` status

2. **vendor_app/admin_kyc_panel.html**
   - Added prompt functions for reject and revoke
   - Updated button onclick handlers
   - Sends rejection_reason to backend

3. **vendor_app/lib/screens/vendor/notifications_screen.dart**
   - Removed `kyc_revoked` notification type
   - Only uses `kyc_rejected` for both cases

## Files to Upload

### SERVER FILES:
1. `vendor_app/server_php/api1_vendor/update_kyc_status.php`
2. `vendor_app/admin_kyc_panel.html`

Upload to:
- `/home/royaldxd/crm.abra-logistic.com/api1/vendor/update_kyc_status.php`
- `/home/royaldxd/crm.abra-logistic.com/admin_kyc_panel.html`

### APP FILES (Rebuild Required):
1. `vendor_app/lib/screens/vendor/notifications_screen.dart`

Run: `flutter build apk`

## Testing

### Test Revoke:
- [ ] Admin approves KYC
- [ ] Vendor sees "✅ Verified" banner
- [ ] Admin clicks "⚠️ Revoke" button
- [ ] Prompt appears for revoke reason
- [ ] Admin enters reason and confirms
- [ ] Status changes to "rejected"
- [ ] Vendor receives "❌ KYC Verification Failed" notification with reason
- [ ] Vendor can re-submit KYC

### Test Re-Approve:
- [ ] KYC is in rejected status
- [ ] Admin clicks "✓ Re-Approve" button
- [ ] Confirms action
- [ ] Status changes to "verified"
- [ ] Vendor receives "✅ KYC Verified Successfully!" notification

### Test Reject:
- [ ] KYC is in submitted status
- [ ] Admin clicks "✗ Reject" button
- [ ] Prompt appears for rejection reason
- [ ] Admin enters reason and confirms
- [ ] Status changes to "rejected"
- [ ] Vendor receives notification with reason

## Summary

**Before**: Revoke changed status to `submitted` (different from customer KYC)
**After**: Revoke changes status to `rejected` with reason (same as customer KYC)

**Result**: ✅ Vendor KYC now works **exactly like** Customer KYC in abra_app

---

**Status**: ✅ COMPLETE
**Pattern**: ✅ Matches Customer KYC
**Ready for Upload**: YES
