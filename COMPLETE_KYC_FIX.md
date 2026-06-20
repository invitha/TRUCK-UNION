# Complete KYC System Fix

## Issues Fixed
1. ✅ KYC data now saves to database
2. ⏳ Document viewing (photos not found) - NEEDS FIX
3. ⏳ Show "Verification Under Process" after submission - NEEDS FIX  
4. ⏳ Block "My Fleet" until KYC verified - NEEDS FIX

## Files to Upload

### 1. upload_kyc_documents.php (CRITICAL - Already Done)
**Path:** `/home/royaldxd/crm.abra-logistic.com/api1/vendor/upload_kyc_documents.php`
**Source:** `vendor_app/server_php/api1_vendor/upload_kyc_documents_FINAL.php`
**Status:** ✅ DONE - Data is now saving

### 2. serve_kyc_image.php (NEW - For Document Viewing)
**Path:** `/home/royaldxd/crm.abra-logistic.com/serve_kyc_image.php`
**Purpose:** Serves uploaded KYC documents securely

```php
<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$uid = $_GET['uid'] ?? '';
$file = $_GET['file'] ?? '';

if (empty($uid) || empty($file)) {
    http_response_code(400);
    die('Missing parameters');
}

$file = basename($file);
$uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $uid);

$upload_dir = '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents';
$file_path = $upload_dir . '/' . $uid . '/' . $file;

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'webp' => 'image/webp'
];

header('Content-Type: ' . ($content_types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
?>
```

### 3. Update Admin Panel JavaScript
In `admin_kyc_panel.html`, find where documents are displayed and change URLs to:
```javascript
const baseUrl = 'https://crm.abra-logistic.com/serve_kyc_image.php';
const aadhaarUrl = `${baseUrl}?uid=${kyc.firebase_uid}&file=${getFilename(kyc.aadhaar_doc)}`;
```

## App Changes Needed

### 1. Show "Verification Under Process" Status
**File:** `vendor_app/lib/screens/vendor/kyc_verification_screen.dart`

After successful submission, the app should:
- Show status as "Under Review" 
- Disable form editing
- Show message: "Your KYC is under review. You'll be notified once verified."

### 2. Block "My Fleet" Until KYC Verified
**File:** `vendor_app/lib/screens/vendor/my_vehicles_screen.dart`

Add KYC check:
```dart
if (kycStatus != 'verified') {
  return Center(
    child: Column(
      children: [
        Icon(Icons.lock, size: 64, color: Colors.grey),
        Text('Complete KYC verification to access fleet management'),
        ElevatedButton(
          onPressed: () => Navigator.pushNamed(context, '/vendor/kyc'),
          child: Text('Complete KYC'),
        ),
      ],
    ),
  );
}
```

## Testing Checklist

1. ✅ Submit KYC from app - Data saves to database
2. ⏳ View documents in admin panel - Should load images
3. ⏳ After submission, app shows "Under Review" status
4. ⏳ Try to access "My Fleet" - Should be blocked until verified
5. ⏳ Admin approves KYC - App should update status
6. ⏳ After approval, "My Fleet" becomes accessible

## Next Steps

1. Upload `serve_kyc_image.php` to server root
2. Update admin panel to use new image URLs
3. Update Flutter app to show verification status
4. Add KYC gate to My Fleet screen
